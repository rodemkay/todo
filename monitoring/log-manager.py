#!/usr/bin/env python3
"""
========================================
WEBHOOK SYSTEM LOG MANAGEMENT
========================================
Intelligente Log-Rotation und Archivierung
- Automatische Log-Rotation
- Komprimierung alter Logs
- Disk-Space Management
- Log-Level basierte Bereinigung
- Performance-optimierte Archivierung
"""

import os
import sys
import gzip
import json
import time
import shutil
import sqlite3
import threading
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional, Tuple
from dataclasses import dataclass, asdict
import subprocess
import re

@dataclass
class LogConfig:
    max_file_size_mb: int = 50
    max_age_days: int = 30
    max_total_size_gb: float = 5.0
    compression_enabled: bool = True
    archive_directory: str = "/tmp/webhook_logs_archive"
    cleanup_interval_hours: int = 6
    log_levels_to_keep: List[str] = None
    
    def __post_init__(self):
        if self.log_levels_to_keep is None:
            self.log_levels_to_keep = ["ERROR", "WARNING", "CRITICAL"]

@dataclass
class LogFile:
    path: str
    size_bytes: int
    age_days: float
    last_modified: float
    compressed: bool = False

@dataclass
class LogStats:
    total_files: int = 0
    total_size_mb: float = 0.0
    compressed_files: int = 0
    compressed_size_mb: float = 0.0
    archived_files: int = 0
    deleted_files: int = 0
    space_saved_mb: float = 0.0

class WebhookLogManager:
    def __init__(self, config: LogConfig = None):
        self.config = config or LogConfig()
        self.stats = LogStats()
        self.running = False
        self.cleanup_thread = None
        
        # Log files to manage
        self.managed_logs = [
            "/tmp/claude_local_trigger.log",
            "/tmp/webhook_performance.log", 
            "/tmp/webhook_monitor.log",
            "/tmp/webhook_queue.log",
            "/tmp/claude_trigger.log",
            "/home/rodemkay/www/react/todo/hooks/logs/todo_*.log"
        ]
        
        # Ensure archive directory exists
        os.makedirs(self.config.archive_directory, exist_ok=True)
        
        self.log("Log manager initialized", "INFO")
        self.log(f"Archive directory: {self.config.archive_directory}", "INFO")
        self.log(f"Managing {len(self.managed_logs)} log patterns", "INFO")
    
    def log(self, message: str, level: str = "INFO"):
        """Log to system log with timestamp"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
        log_entry = f"{timestamp} [LOG_MGR] [{level}] {message}"
        print(log_entry)
        
        # Also write to dedicated log manager log
        try:
            with open("/tmp/log_manager.log", "a") as f:
                f.write(log_entry + "\\n")
        except Exception:
            pass
    
    def get_all_log_files(self) -> List[LogFile]:
        """Get all log files matching managed patterns"""
        log_files = []
        
        for pattern in self.managed_logs:
            if '*' in pattern:
                # Handle wildcards
                pattern_dir = os.path.dirname(pattern)
                pattern_name = os.path.basename(pattern)
                
                if os.path.exists(pattern_dir):
                    import glob
                    matching_files = glob.glob(pattern)
                    for file_path in matching_files:
                        if os.path.isfile(file_path):
                            log_files.append(self.create_log_file_info(file_path))
            else:
                # Direct file path
                if os.path.isfile(pattern):
                    log_files.append(self.create_log_file_info(pattern))
        
        return log_files
    
    def create_log_file_info(self, file_path: str) -> LogFile:
        """Create LogFile object with file statistics"""
        try:
            stat = os.stat(file_path)
            size_bytes = stat.st_size
            last_modified = stat.st_mtime
            age_days = (time.time() - last_modified) / 86400
            compressed = file_path.endswith('.gz')
            
            return LogFile(
                path=file_path,
                size_bytes=size_bytes,
                age_days=age_days,
                last_modified=last_modified,
                compressed=compressed
            )
        except Exception as e:
            self.log(f"Failed to get info for {file_path}: {e}", "ERROR")
            return None
    
    def rotate_log_file(self, log_file: LogFile) -> bool:
        """Rotate a single log file"""
        try:
            timestamp = datetime.fromtimestamp(log_file.last_modified).strftime("%Y%m%d_%H%M%S")
            base_name = os.path.basename(log_file.path)
            rotated_name = f"{base_name}.{timestamp}"
            
            # Move to archive directory
            archive_path = os.path.join(self.config.archive_directory, rotated_name)
            shutil.move(log_file.path, archive_path)
            
            # Compress if enabled
            if self.config.compression_enabled:
                compressed_path = f"{archive_path}.gz"
                
                with open(archive_path, 'rb') as f_in:
                    with gzip.open(compressed_path, 'wb') as f_out:
                        shutil.copyfileobj(f_in, f_out)
                
                # Remove uncompressed file
                os.remove(archive_path)
                
                # Calculate compression ratio
                original_size = log_file.size_bytes
                compressed_size = os.path.getsize(compressed_path)
                compression_ratio = (1 - compressed_size / original_size) * 100
                
                self.log(f"Rotated and compressed {log_file.path} -> {compressed_path} "
                        f"(saved {compression_ratio:.1f}%)", "INFO")
                
                self.stats.compressed_files += 1
                self.stats.space_saved_mb += (original_size - compressed_size) / 1024 / 1024
            else:
                self.log(f"Rotated {log_file.path} -> {archive_path}", "INFO")
            
            self.stats.archived_files += 1
            return True
            
        except Exception as e:
            self.log(f"Failed to rotate {log_file.path}: {e}", "ERROR")
            return False
    
    def should_rotate_file(self, log_file: LogFile) -> bool:
        """Determine if file should be rotated"""
        size_mb = log_file.size_bytes / 1024 / 1024
        
        # Size-based rotation
        if size_mb > self.config.max_file_size_mb:
            self.log(f"{log_file.path} exceeds size limit: {size_mb:.1f}MB", "DEBUG")
            return True
        
        # Age-based rotation (only if file is not actively growing)
        if log_file.age_days > 1:  # At least 1 day old
            # Check if file has recent activity (less than 1 hour)
            inactive_hours = (time.time() - log_file.last_modified) / 3600
            if inactive_hours > 1 and size_mb > 1:  # Inactive and has content
                self.log(f"{log_file.path} is inactive for {inactive_hours:.1f}h", "DEBUG")
                return True
        
        return False
    
    def cleanup_old_archives(self):
        """Clean up old archived files"""
        try:
            current_time = time.time()
            total_size = 0
            archive_files = []
            
            # Get all files in archive directory
            for file_path in Path(self.config.archive_directory).glob("*"):
                if file_path.is_file():
                    stat = file_path.stat()
                    age_days = (current_time - stat.st_mtime) / 86400
                    
                    archive_files.append({
                        'path': str(file_path),
                        'age_days': age_days,
                        'size_bytes': stat.st_size,
                        'mtime': stat.st_mtime
                    })
                    
                    total_size += stat.st_size
            
            # Sort by age (oldest first)
            archive_files.sort(key=lambda x: x['mtime'])
            
            total_size_gb = total_size / 1024 / 1024 / 1024
            self.log(f"Archive directory: {len(archive_files)} files, {total_size_gb:.2f}GB", "INFO")
            
            # Age-based cleanup
            for file_info in archive_files[:]:  # Copy list to modify during iteration
                if file_info['age_days'] > self.config.max_age_days:
                    try:
                        os.remove(file_info['path'])
                        self.log(f"Deleted old archive: {os.path.basename(file_info['path'])} "
                                f"(age: {file_info['age_days']:.1f} days)", "INFO")
                        self.stats.deleted_files += 1
                        archive_files.remove(file_info)
                        total_size -= file_info['size_bytes']
                    except Exception as e:
                        self.log(f"Failed to delete {file_info['path']}: {e}", "ERROR")
            
            # Size-based cleanup (if still over limit)
            total_size_gb = total_size / 1024 / 1024 / 1024
            if total_size_gb > self.config.max_total_size_gb:
                self.log(f"Archive size {total_size_gb:.2f}GB exceeds limit {self.config.max_total_size_gb}GB", "WARNING")
                
                # Delete oldest files until under limit
                for file_info in archive_files:
                    if total_size_gb <= self.config.max_total_size_gb:
                        break
                    
                    try:
                        os.remove(file_info['path'])
                        self.log(f"Deleted for size limit: {os.path.basename(file_info['path'])}", "INFO")
                        self.stats.deleted_files += 1
                        total_size -= file_info['size_bytes']
                        total_size_gb = total_size / 1024 / 1024 / 1024
                    except Exception as e:
                        self.log(f"Failed to delete {file_info['path']}: {e}", "ERROR")
            
        except Exception as e:
            self.log(f"Archive cleanup failed: {e}", "ERROR")
    
    def extract_important_logs(self, log_file: LogFile) -> Optional[str]:
        """Extract important log entries before rotation"""
        if not os.path.exists(log_file.path):
            return None
        
        important_entries = []
        
        try:
            with open(log_file.path, 'r', encoding='utf-8', errors='ignore') as f:
                for line_num, line in enumerate(f, 1):
                    # Check if line contains important log level
                    line_upper = line.upper()
                    for level in self.config.log_levels_to_keep:
                        if f"[{level}]" in line_upper or f" {level} " in line_upper:
                            important_entries.append(f"L{line_num}: {line.strip()}")
                            break
            
            if important_entries:
                # Save important entries to separate file
                important_log_path = os.path.join(
                    self.config.archive_directory,
                    f"important_{os.path.basename(log_file.path)}_{int(time.time())}.log"
                )
                
                with open(important_log_path, 'w') as f:
                    f.write(f"# Important log entries extracted from {log_file.path}\\n")
                    f.write(f"# Extraction time: {datetime.now().isoformat()}\\n")
                    f.write(f"# Total entries: {len(important_entries)}\\n\\n")
                    f.write("\\n".join(important_entries))
                
                self.log(f"Extracted {len(important_entries)} important entries to {important_log_path}", "INFO")
                return important_log_path
        
        except Exception as e:
            self.log(f"Failed to extract important logs from {log_file.path}: {e}", "ERROR")
        
        return None
    
    def run_cleanup_cycle(self):
        """Run a complete cleanup cycle"""
        self.log("Starting cleanup cycle", "INFO")
        cycle_start = time.time()
        
        # Reset stats for this cycle
        self.stats = LogStats()
        
        try:
            # Get all log files
            log_files = self.get_all_log_files()
            self.log(f"Found {len(log_files)} log files to check", "INFO")
            
            # Calculate total size
            total_size = sum(lf.size_bytes for lf in log_files if lf)
            self.stats.total_files = len([lf for lf in log_files if lf])
            self.stats.total_size_mb = total_size / 1024 / 1024
            
            self.log(f"Total log size: {self.stats.total_size_mb:.1f}MB", "INFO")
            
            # Process each log file
            for log_file in log_files:
                if log_file is None:
                    continue
                
                if self.should_rotate_file(log_file):
                    self.log(f"Rotating: {log_file.path} ({log_file.size_bytes/1024/1024:.1f}MB, "
                            f"{log_file.age_days:.1f} days old)", "INFO")
                    
                    # Extract important logs first
                    self.extract_important_logs(log_file)
                    
                    # Rotate the file
                    if self.rotate_log_file(log_file):
                        # Create empty replacement file (for active logs)
                        if not log_file.path.endswith('.log') or 'archive' not in log_file.path:
                            try:
                                Path(log_file.path).touch()
                                self.log(f"Created new empty log: {log_file.path}", "DEBUG")
                            except Exception as e:
                                self.log(f"Failed to create new log file: {e}", "WARNING")
            
            # Clean up old archives
            self.cleanup_old_archives()
            
            # Update compressed stats
            archive_files = list(Path(self.config.archive_directory).glob("*.gz"))
            self.stats.compressed_files = len(archive_files)
            self.stats.compressed_size_mb = sum(f.stat().st_size for f in archive_files) / 1024 / 1024
            
            cycle_duration = time.time() - cycle_start
            
            self.log(f"Cleanup cycle completed in {cycle_duration:.2f}s", "INFO")
            self.log(f"Stats: {self.stats.archived_files} archived, {self.stats.compressed_files} compressed, "
                    f"{self.stats.deleted_files} deleted, {self.stats.space_saved_mb:.1f}MB saved", "INFO")
            
        except Exception as e:
            self.log(f"Cleanup cycle failed: {e}", "ERROR")
    
    def start_automatic_cleanup(self):
        """Start automatic cleanup in background thread"""
        if self.running:
            self.log("Automatic cleanup already running", "WARNING")
            return
        
        self.running = True
        
        def cleanup_worker():
            self.log("Automatic cleanup thread started", "INFO")
            
            while self.running:
                try:
                    # Run cleanup cycle
                    self.run_cleanup_cycle()
                    
                    # Wait for next cycle
                    sleep_seconds = self.config.cleanup_interval_hours * 3600
                    
                    for _ in range(int(sleep_seconds)):
                        if not self.running:
                            break
                        time.sleep(1)
                
                except Exception as e:
                    self.log(f"Automatic cleanup error: {e}", "ERROR")
                    time.sleep(60)  # Wait 1 minute before retrying
            
            self.log("Automatic cleanup thread stopped", "INFO")
        
        self.cleanup_thread = threading.Thread(target=cleanup_worker, daemon=True)
        self.cleanup_thread.start()
        
        self.log(f"Automatic cleanup started (interval: {self.config.cleanup_interval_hours}h)", "INFO")
    
    def stop_automatic_cleanup(self):
        """Stop automatic cleanup"""
        self.running = False
        
        if self.cleanup_thread and self.cleanup_thread.is_alive():
            self.log("Stopping automatic cleanup...", "INFO")
            self.cleanup_thread.join(timeout=10)
            
            if self.cleanup_thread.is_alive():
                self.log("Cleanup thread did not stop gracefully", "WARNING")
            else:
                self.log("Automatic cleanup stopped", "INFO")
    
    def get_status_report(self) -> Dict:
        """Get current status report"""
        log_files = self.get_all_log_files()
        valid_files = [lf for lf in log_files if lf]
        
        current_total_size = sum(lf.size_bytes for lf in valid_files)
        
        # Archive info
        archive_files = []
        if os.path.exists(self.config.archive_directory):
            for file_path in Path(self.config.archive_directory).glob("*"):
                if file_path.is_file():
                    archive_files.append(file_path.stat().st_size)
        
        archive_total_size = sum(archive_files)
        
        return {
            "config": asdict(self.config),
            "stats": asdict(self.stats),
            "current_status": {
                "active_log_files": len(valid_files),
                "active_total_size_mb": current_total_size / 1024 / 1024,
                "archive_files": len(archive_files),
                "archive_total_size_mb": archive_total_size / 1024 / 1024,
                "cleanup_running": self.running
            },
            "log_files": [
                {
                    "path": lf.path,
                    "size_mb": lf.size_bytes / 1024 / 1024,
                    "age_days": lf.age_days,
                    "should_rotate": self.should_rotate_file(lf)
                }
                for lf in valid_files
            ]
        }
    
    def optimize_log_file(self, log_path: str) -> bool:
        """Optimize a specific log file by removing redundant entries"""
        if not os.path.exists(log_path):
            return False
        
        try:
            # Read all lines
            with open(log_path, 'r', encoding='utf-8', errors='ignore') as f:
                lines = f.readlines()
            
            # Remove duplicate consecutive entries
            optimized_lines = []
            last_line = None
            duplicate_count = 0
            
            for line in lines:
                line = line.strip()
                
                if line == last_line:
                    duplicate_count += 1
                else:
                    if duplicate_count > 0:
                        optimized_lines.append(f"# [Previous line repeated {duplicate_count} times]\\n")
                        duplicate_count = 0
                    
                    optimized_lines.append(line + "\\n")
                    last_line = line
            
            # Handle final duplicates
            if duplicate_count > 0:
                optimized_lines.append(f"# [Previous line repeated {duplicate_count} times]\\n")
            
            # Write optimized file if there are savings
            if len(optimized_lines) < len(lines):
                with open(log_path, 'w') as f:
                    f.writelines(optimized_lines)
                
                saved_lines = len(lines) - len(optimized_lines)
                self.log(f"Optimized {log_path}: removed {saved_lines} duplicate lines", "INFO")
                return True
            
            return False
            
        except Exception as e:
            self.log(f"Failed to optimize {log_path}: {e}", "ERROR")
            return False

def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Webhook Log Manager')
    parser.add_argument('--config-file', type=str, help='JSON config file path')
    parser.add_argument('--run-once', action='store_true', help='Run cleanup once and exit')
    parser.add_argument('--start-daemon', action='store_true', help='Start automatic cleanup daemon')
    parser.add_argument('--status', action='store_true', help='Show status report')
    parser.add_argument('--optimize', type=str, help='Optimize specific log file')
    parser.add_argument('--max-size-mb', type=int, default=50, help='Max file size in MB')
    parser.add_argument('--max-age-days', type=int, default=30, help='Max file age in days')
    parser.add_argument('--archive-dir', type=str, help='Archive directory path')
    
    args = parser.parse_args()
    
    # Load config
    config = LogConfig()
    
    if args.config_file and os.path.exists(args.config_file):
        try:
            with open(args.config_file, 'r') as f:
                config_data = json.load(f)
                
            # Update config with loaded values
            for key, value in config_data.items():
                if hasattr(config, key):
                    setattr(config, key, value)
                    
        except Exception as e:
            print(f"Failed to load config: {e}")
            sys.exit(1)
    
    # Override with command line args
    config.max_file_size_mb = args.max_size_mb
    config.max_age_days = args.max_age_days
    
    if args.archive_dir:
        config.archive_directory = args.archive_dir
    
    # Create log manager
    log_manager = WebhookLogManager(config)
    
    try:
        if args.optimize:
            # Optimize specific file
            if log_manager.optimize_log_file(args.optimize):
                print(f"Successfully optimized {args.optimize}")
            else:
                print(f"No optimization needed for {args.optimize}")
        
        elif args.status:
            # Show status
            status = log_manager.get_status_report()
            print(json.dumps(status, indent=2))
        
        elif args.run_once:
            # Run cleanup once
            log_manager.run_cleanup_cycle()
        
        elif args.start_daemon:
            # Start daemon
            log_manager.start_automatic_cleanup()
            
            print("Log cleanup daemon started. Press Ctrl+C to stop.")
            
            try:
                while log_manager.running:
                    time.sleep(1)
            except KeyboardInterrupt:
                print("\\nShutdown requested...")
            finally:
                log_manager.stop_automatic_cleanup()
        
        else:
            print("No action specified. Use --help for options.")
    
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()