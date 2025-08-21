#!/usr/bin/env python3
"""
Hook System Test Suite
Testet alle Komponenten des Hook-Systems
"""

import json
import os
import subprocess
import time
from pathlib import Path
import sys
import tempfile

# Konfiguration laden
CONFIG_PATH = Path(__file__).parent / "config.json"
with open(CONFIG_PATH) as f:
    CONFIG = json.load(f)

class TestResult:
    def __init__(self, name):
        self.name = name
        self.passed = False
        self.error = None
        self.duration = 0
        
class HookSystemTests:
    def __init__(self):
        self.tests = []
        self.passed = 0
        self.failed = 0
        
    def run_test(self, name, test_func):
        """Führt einen einzelnen Test aus"""
        result = TestResult(name)
        start_time = time.time()
        
        try:
            test_func()
            result.passed = True
            self.passed += 1
            print(f"✅ {name}")
        except Exception as e:
            result.error = str(e)
            self.failed += 1
            print(f"❌ {name}: {e}")
        
        result.duration = time.time() - start_time
        self.tests.append(result)
        return result
        
    def test_db_connection(self):
        """Test: Datenbankverbindung"""
        cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} \"cd {CONFIG['database']['remote_path']} && wp db query 'SELECT 1'\""
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
        assert result.returncode == 0, "Database connection failed"
        
    def test_todo_creation(self):
        """Test: Todo erstellen"""
        test_title = "TEST_TODO_" + str(int(time.time()))
        query = f"INSERT INTO {CONFIG['database']['table_prefix']}project_todos (title, description, status, bearbeiten) VALUES ('{test_title}', 'Test Description', 'offen', 1)"
        
        cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} \"cd {CONFIG['database']['remote_path']} && wp db query \\\"{query}\\\"\""
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        assert result.returncode == 0, "Failed to create test todo"
        
        # Get created ID
        cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} \"cd {CONFIG['database']['remote_path']} && wp db query \\\"SELECT id FROM {CONFIG['database']['table_prefix']}project_todos WHERE title='{test_title}' LIMIT 1\\\"\""
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        
        # Clean up
        if "id" not in result.stdout:
            lines = result.stdout.strip().split('\n')
            if len(lines) > 1:
                todo_id = lines[1].strip()
                cleanup_query = f"DELETE FROM {CONFIG['database']['table_prefix']}project_todos WHERE id={todo_id}"
                cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} \"cd {CONFIG['database']['remote_path']} && wp db query \\\"{cleanup_query}\\\"\""
                subprocess.run(cmd, shell=True)
                
    def test_todo_manager_import(self):
        """Test: Todo-Manager Import"""
        import sys
        sys.path.insert(0, str(Path(__file__).parent))
        import todo_manager
        assert hasattr(todo_manager, 'load_todo'), "load_todo function not found"
        assert hasattr(todo_manager, 'complete_todo'), "complete_todo function not found"
        
    def test_output_collector_import(self):
        """Test: Output-Collector Import"""
        import sys
        sys.path.insert(0, str(Path(__file__).parent))
        import output_collector
        assert hasattr(output_collector, 'OutputCollector'), "OutputCollector class not found"
        assert hasattr(output_collector, 'collect_outputs_for_todo'), "collect_outputs_for_todo function not found"
        
    def test_config_structure(self):
        """Test: Konfiguration vollständig"""
        required_keys = ['database', 'behavior', 'paths', 'logging']
        for key in required_keys:
            assert key in CONFIG, f"Missing config key: {key}"
            
        # Database config
        db_keys = ['host', 'user', 'db_name', 'table_prefix', 'remote_path']
        for key in db_keys:
            assert key in CONFIG['database'], f"Missing database config: {key}"
            
    def test_lock_file_creation(self):
        """Test: Lock-Datei Erstellung"""
        test_file = Path("/tmp/TEST_LOCK_" + str(int(time.time())))
        test_file.write_text("test")
        assert test_file.exists(), "Failed to create lock file"
        test_file.unlink()
        assert not test_file.exists(), "Failed to delete lock file"
        
    def test_log_directory(self):
        """Test: Log-Verzeichnis existiert"""
        log_dir = Path(CONFIG["paths"]["logs"])
        assert log_dir.exists(), f"Log directory does not exist: {log_dir}"
        assert log_dir.is_dir(), f"Log path is not a directory: {log_dir}"
        
    def test_ssh_connection(self):
        """Test: SSH-Verbindung"""
        cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} 'echo OK'"
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
        assert result.returncode == 0, "SSH connection failed"
        assert "OK" in result.stdout, "SSH echo test failed"
        
    def test_wp_cli(self):
        """Test: WP-CLI verfügbar"""
        cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} \"cd {CONFIG['database']['remote_path']} && wp --version\""
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        assert result.returncode == 0, "WP-CLI not available"
        assert "WP-CLI" in result.stdout, "WP-CLI version check failed"
        
    def test_monitor_import(self):
        """Test: Monitor Import"""
        import sys
        sys.path.insert(0, str(Path(__file__).parent))
        import monitor
        assert hasattr(monitor, 'HookSystemMonitor'), "HookSystemMonitor class not found"
        
    def run_all_tests(self):
        """Führt alle Tests aus"""
        print("🧪 Hook System Test Suite")
        print("=" * 50)
        
        # Core Tests
        print("\n📦 Core Components:")
        self.run_test("Config Structure", self.test_config_structure)
        self.run_test("Todo Manager Import", self.test_todo_manager_import)
        self.run_test("Output Collector Import", self.test_output_collector_import)
        self.run_test("Monitor Import", self.test_monitor_import)
        
        # Infrastructure Tests
        print("\n🔧 Infrastructure:")
        self.run_test("SSH Connection", self.test_ssh_connection)
        self.run_test("Database Connection", self.test_db_connection)
        self.run_test("WP-CLI Available", self.test_wp_cli)
        self.run_test("Log Directory", self.test_log_directory)
        
        # Functionality Tests
        print("\n⚡ Functionality:")
        self.run_test("Lock File Operations", self.test_lock_file_creation)
        self.run_test("Todo Creation/Deletion", self.test_todo_creation)
        
        # Summary
        print("\n" + "=" * 50)
        print(f"📊 Test Results:")
        print(f"  ✅ Passed: {self.passed}")
        print(f"  ❌ Failed: {self.failed}")
        print(f"  📈 Success Rate: {(self.passed / (self.passed + self.failed) * 100):.1f}%")
        
        if self.failed > 0:
            print("\n❌ Failed Tests:")
            for test in self.tests:
                if not test.passed:
                    print(f"  - {test.name}: {test.error}")
                    
        return self.failed == 0

def main():
    tester = HookSystemTests()
    success = tester.run_all_tests()
    
    if success:
        print("\n✅ All tests passed! Hook system is ready.")
    else:
        print("\n❌ Some tests failed. Please fix the issues and run again.")
        
    sys.exit(0 if success else 1)

if __name__ == "__main__":
    main()