#!/usr/bin/env python3
"""
========================================
WEBHOOK SYSTEM LOAD TESTING
========================================
Comprehensive Load Testing für Webhook-System
- Stress Testing
- Performance Benchmarking
- Scalability Analysis
- Concurrent User Simulation
"""

import os
import sys
import time
import json
import threading
import subprocess
import statistics
from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, asdict
from concurrent.futures import ThreadPoolExecutor, as_completed
import random

@dataclass
class LoadTestConfig:
    test_duration: int = 60  # seconds
    concurrent_users: int = 10
    requests_per_user: int = 100
    ramp_up_time: int = 10  # seconds to reach full load
    think_time_min: float = 0.1  # min delay between requests
    think_time_max: float = 2.0  # max delay between requests
    trigger_file: str = "/tmp/claude_todo_trigger.txt"
    test_commands: List[str] = None

@dataclass
class TestResult:
    timestamp: float
    command: str
    response_time: float
    success: bool
    error_message: Optional[str] = None
    user_id: int = 0

@dataclass
class LoadTestReport:
    config: LoadTestConfig
    start_time: float
    end_time: float
    total_requests: int
    successful_requests: int
    failed_requests: int
    avg_response_time: float
    min_response_time: float
    max_response_time: float
    p50_response_time: float
    p95_response_time: float
    p99_response_time: float
    requests_per_second: float
    error_rate: float
    concurrent_users_achieved: int
    results: List[TestResult]

class WebhookLoadTester:
    def __init__(self, config: LoadTestConfig):
        self.config = config
        self.results: List[TestResult] = []
        self.start_time: float = 0
        self.end_time: float = 0
        self.active_users: int = 0
        self.results_lock = threading.Lock()
        
        # Default test commands if none provided
        if not self.config.test_commands:
            self.config.test_commands = [
                "./todo",
                "./todo status",
                "./todo -id 123",
                "./todo complete",
                "./todo test",
                "./todo monitor"
            ]
        
        self.log("Load tester initialized with config:", "INFO")
        self.log(json.dumps(asdict(self.config), indent=2), "INFO")
    
    def log(self, message: str, level: str = "INFO"):
        """Thread-safe logging"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
        thread_id = threading.current_thread().name
        print(f"{timestamp} [{level}] [{thread_id}] {message}")
    
    def send_trigger(self, command: str, user_id: int) -> TestResult:
        """Send single trigger and measure response time"""
        start_time = time.time()
        success = False
        error_message = None
        
        try:
            # Write command to trigger file (atomic operation)
            temp_file = f"{self.config.trigger_file}.tmp.{user_id}.{int(time.time())}"
            
            with open(temp_file, 'w') as f:
                f.write(command)
            
            # Atomic move
            os.rename(temp_file, self.config.trigger_file)
            
            # Wait for trigger to be processed (check if file is gone)
            max_wait = 10.0  # Maximum wait time
            wait_start = time.time()
            
            while os.path.exists(self.config.trigger_file) and (time.time() - wait_start) < max_wait:
                time.sleep(0.01)  # 10ms polling
            
            if not os.path.exists(self.config.trigger_file):
                success = True
            else:
                # Cleanup if still exists
                try:
                    os.remove(self.config.trigger_file)
                except:
                    pass
                error_message = "Trigger processing timeout"
                
        except Exception as e:
            error_message = str(e)
            # Cleanup
            try:
                if os.path.exists(temp_file):
                    os.remove(temp_file)
            except:
                pass
        
        response_time = time.time() - start_time
        
        return TestResult(
            timestamp=start_time,
            command=command,
            response_time=response_time,
            success=success,
            error_message=error_message,
            user_id=user_id
        )
    
    def simulate_user(self, user_id: int, requests_per_user: int, 
                     start_delay: float) -> List[TestResult]:
        """Simulate single user behavior"""
        time.sleep(start_delay)  # Ramp-up delay
        
        self.log(f"User {user_id} started (delay: {start_delay:.2f}s)", "INFO")
        user_results = []
        
        with self.results_lock:
            self.active_users += 1
        
        try:
            for i in range(requests_per_user):
                # Check if test should continue
                if time.time() > self.end_time:
                    break
                
                # Select random command
                command = random.choice(self.config.test_commands)
                
                # Send trigger and measure
                result = self.send_trigger(command, user_id)
                user_results.append(result)
                
                # Log every 10th request
                if (i + 1) % 10 == 0:
                    self.log(f"User {user_id}: {i+1}/{requests_per_user} requests completed", "DEBUG")
                
                # Think time (simulate user delay)
                if i < requests_per_user - 1:  # No delay after last request
                    think_time = random.uniform(
                        self.config.think_time_min,
                        self.config.think_time_max
                    )
                    time.sleep(think_time)
        
        except Exception as e:
            self.log(f"User {user_id} error: {e}", "ERROR")
        
        finally:
            with self.results_lock:
                self.active_users -= 1
            
            self.log(f"User {user_id} completed {len(user_results)} requests", "INFO")
        
        return user_results
    
    def run_load_test(self) -> LoadTestReport:
        """Run the complete load test"""
        self.log("Starting load test...", "INFO")
        
        # Prepare test
        self.start_time = time.time()
        self.end_time = self.start_time + self.config.test_duration
        self.results = []
        self.active_users = 0
        
        # Calculate ramp-up delays
        ramp_delays = []
        if self.config.concurrent_users > 1:
            for i in range(self.config.concurrent_users):
                delay = (i * self.config.ramp_up_time) / (self.config.concurrent_users - 1)
                ramp_delays.append(delay)
        else:
            ramp_delays = [0]
        
        # Start concurrent users
        all_results = []
        
        with ThreadPoolExecutor(max_workers=self.config.concurrent_users) as executor:
            # Submit user simulation tasks
            futures = []
            for user_id in range(self.config.concurrent_users):
                future = executor.submit(
                    self.simulate_user,
                    user_id,
                    self.config.requests_per_user,
                    ramp_delays[user_id]
                )
                futures.append(future)
            
            # Monitor progress
            completed_users = 0
            while completed_users < len(futures):
                time.sleep(1)
                
                # Count completed futures
                completed_users = sum(1 for f in futures if f.done())
                
                # Log progress
                elapsed = time.time() - self.start_time
                self.log(f"Progress: {completed_users}/{len(futures)} users completed, "
                        f"Active: {self.active_users}, Elapsed: {elapsed:.1f}s", "STATUS")
                
                # Check for timeout
                if elapsed > self.config.test_duration + 30:  # 30s grace period
                    self.log("Test timeout reached, cancelling remaining tasks", "WARNING")
                    for f in futures:
                        f.cancel()
                    break
            
            # Collect results
            for future in as_completed(futures, timeout=30):
                try:
                    user_results = future.result()
                    all_results.extend(user_results)
                except Exception as e:
                    self.log(f"Failed to get user results: {e}", "ERROR")
        
        # Store all results
        self.results = all_results
        actual_end_time = time.time()
        
        self.log(f"Load test completed. Collected {len(self.results)} results", "INFO")
        
        # Generate report
        return self.generate_report(actual_end_time)
    
    def generate_report(self, actual_end_time: float) -> LoadTestReport:
        """Generate comprehensive test report"""
        if not self.results:
            raise Exception("No results to analyze")
        
        # Basic statistics
        total_requests = len(self.results)
        successful_results = [r for r in self.results if r.success]
        failed_results = [r for r in self.results if not r.success]
        
        successful_requests = len(successful_results)
        failed_requests = len(failed_results)
        
        # Response time statistics
        response_times = [r.response_time for r in successful_results]
        
        if response_times:
            avg_response_time = statistics.mean(response_times)
            min_response_time = min(response_times)
            max_response_time = max(response_times)
            
            # Percentiles
            sorted_times = sorted(response_times)
            p50_response_time = statistics.median(sorted_times)
            p95_index = int(0.95 * len(sorted_times))
            p99_index = int(0.99 * len(sorted_times))
            
            p95_response_time = sorted_times[min(p95_index, len(sorted_times) - 1)]
            p99_response_time = sorted_times[min(p99_index, len(sorted_times) - 1)]
        else:
            avg_response_time = min_response_time = max_response_time = 0
            p50_response_time = p95_response_time = p99_response_time = 0
        
        # Calculate requests per second
        actual_duration = actual_end_time - self.start_time
        requests_per_second = total_requests / actual_duration if actual_duration > 0 else 0
        
        # Error rate
        error_rate = (failed_requests / total_requests * 100) if total_requests > 0 else 0
        
        # Concurrent users achieved (peak)
        max_concurrent = self.config.concurrent_users
        
        return LoadTestReport(
            config=self.config,
            start_time=self.start_time,
            end_time=actual_end_time,
            total_requests=total_requests,
            successful_requests=successful_requests,
            failed_requests=failed_requests,
            avg_response_time=avg_response_time,
            min_response_time=min_response_time,
            max_response_time=max_response_time,
            p50_response_time=p50_response_time,
            p95_response_time=p95_response_time,
            p99_response_time=p99_response_time,
            requests_per_second=requests_per_second,
            error_rate=error_rate,
            concurrent_users_achieved=max_concurrent,
            results=self.results
        )
    
    def print_report(self, report: LoadTestReport):
        """Print formatted test report"""
        print("\\n" + "="*80)
        print("WEBHOOK SYSTEM LOAD TEST REPORT")
        print("="*80)
        
        # Test Configuration
        print("\\nTEST CONFIGURATION:")
        print(f"  Duration:           {report.config.test_duration}s")
        print(f"  Concurrent Users:   {report.config.concurrent_users}")
        print(f"  Requests per User:  {report.config.requests_per_user}")
        print(f"  Ramp-up Time:       {report.config.ramp_up_time}s")
        print(f"  Think Time:         {report.config.think_time_min}-{report.config.think_time_max}s")
        
        # Test Results
        print("\\nTEST RESULTS:")
        duration = report.end_time - report.start_time
        print(f"  Actual Duration:    {duration:.2f}s")
        print(f"  Total Requests:     {report.total_requests:,}")
        print(f"  Successful:         {report.successful_requests:,} ({100-report.error_rate:.1f}%)")
        print(f"  Failed:             {report.failed_requests:,} ({report.error_rate:.1f}%)")
        print(f"  Requests/Second:    {report.requests_per_second:.2f}")
        
        # Response Time Statistics
        print("\\nRESPONSE TIME STATISTICS:")
        print(f"  Average:            {report.avg_response_time*1000:.1f}ms")
        print(f"  Minimum:            {report.min_response_time*1000:.1f}ms")
        print(f"  Maximum:            {report.max_response_time*1000:.1f}ms")
        print(f"  50th Percentile:    {report.p50_response_time*1000:.1f}ms")
        print(f"  95th Percentile:    {report.p95_response_time*1000:.1f}ms")
        print(f"  99th Percentile:    {report.p99_response_time*1000:.1f}ms")
        
        # Performance Analysis
        print("\\nPERFORMANCE ANALYSIS:")
        
        # Latency goals
        if report.p95_response_time <= 1.0:  # 1 second
            print("  ✅ 95th percentile response time: EXCELLENT (≤1s)")
        elif report.p95_response_time <= 2.0:
            print("  ⚠️  95th percentile response time: GOOD (≤2s)")
        else:
            print("  ❌ 95th percentile response time: POOR (>2s)")
        
        # Throughput
        if report.requests_per_second >= 10:
            print("  ✅ Throughput: EXCELLENT (≥10 req/s)")
        elif report.requests_per_second >= 5:
            print("  ⚠️  Throughput: GOOD (≥5 req/s)")
        else:
            print("  ❌ Throughput: POOR (<5 req/s)")
        
        # Error rate
        if report.error_rate <= 1.0:
            print("  ✅ Error rate: EXCELLENT (≤1%)")
        elif report.error_rate <= 5.0:
            print("  ⚠️  Error rate: ACCEPTABLE (≤5%)")
        else:
            print("  ❌ Error rate: POOR (>5%)")
        
        # Error details
        if report.failed_requests > 0:
            print("\\nERROR ANALYSIS:")
            error_counts = {}
            for result in report.results:
                if not result.success and result.error_message:
                    error_counts[result.error_message] = error_counts.get(result.error_message, 0) + 1
            
            for error, count in sorted(error_counts.items(), key=lambda x: x[1], reverse=True):
                print(f"  {error}: {count} occurrences")
        
        # Recommendations
        print("\\nRECOMMENDATIONS:")
        
        if report.error_rate > 5:
            print("  • High error rate detected - investigate system capacity")
        
        if report.p95_response_time > 2.0:
            print("  • Response times are high - consider performance optimizations")
        
        if report.requests_per_second < 5:
            print("  • Low throughput - check for bottlenecks in processing pipeline")
        
        if report.error_rate == 0 and report.p95_response_time < 1.0:
            print("  • System performing excellently - ready for production load")
        
        print("\\n" + "="*80)
    
    def save_report(self, report: LoadTestReport, filename: str):
        """Save report to JSON file"""
        report_data = asdict(report)
        # Convert results to JSON-serializable format
        report_data['results'] = [asdict(r) for r in report.results]
        
        with open(filename, 'w') as f:
            json.dump(report_data, f, indent=2)
        
        self.log(f"Report saved to {filename}", "INFO")

def run_stress_test():
    """Run high-stress test scenario"""
    config = LoadTestConfig(
        test_duration=30,
        concurrent_users=20,
        requests_per_user=50,
        ramp_up_time=5,
        think_time_min=0.05,
        think_time_max=0.5
    )
    
    tester = WebhookLoadTester(config)
    return tester.run_load_test()

def run_baseline_test():
    """Run baseline performance test"""
    config = LoadTestConfig(
        test_duration=60,
        concurrent_users=5,
        requests_per_user=100,
        ramp_up_time=10,
        think_time_min=0.1,
        think_time_max=1.0
    )
    
    tester = WebhookLoadTester(config)
    return tester.run_load_test()

def run_scalability_test():
    """Run scalability test with increasing load"""
    results = {}
    user_counts = [1, 5, 10, 15, 20]
    
    for users in user_counts:
        print(f"\\nRunning scalability test with {users} concurrent users...")
        
        config = LoadTestConfig(
            test_duration=30,
            concurrent_users=users,
            requests_per_user=20,
            ramp_up_time=5,
            think_time_min=0.1,
            think_time_max=0.5
        )
        
        tester = WebhookLoadTester(config)
        report = tester.run_load_test()
        
        results[users] = {
            'requests_per_second': report.requests_per_second,
            'avg_response_time': report.avg_response_time,
            'error_rate': report.error_rate,
            'p95_response_time': report.p95_response_time
        }
        
        print(f"Users: {users}, RPS: {report.requests_per_second:.2f}, "
              f"Avg Response: {report.avg_response_time*1000:.1f}ms, "
              f"Error Rate: {report.error_rate:.1f}%")
    
    return results

def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Webhook System Load Tester')
    parser.add_argument('--test-type', choices=['baseline', 'stress', 'scalability', 'custom'], 
                       default='baseline', help='Type of test to run')
    parser.add_argument('--duration', type=int, default=60, help='Test duration in seconds')
    parser.add_argument('--users', type=int, default=10, help='Number of concurrent users')
    parser.add_argument('--requests', type=int, default=100, help='Requests per user')
    parser.add_argument('--ramp-up', type=int, default=10, help='Ramp-up time in seconds')
    parser.add_argument('--output', type=str, help='Output file for results')
    parser.add_argument('--trigger-file', type=str, default='/tmp/claude_todo_trigger.txt',
                       help='Path to trigger file')
    
    args = parser.parse_args()
    
    try:
        if args.test_type == 'baseline':
            report = run_baseline_test()
        elif args.test_type == 'stress':
            report = run_stress_test()
        elif args.test_type == 'scalability':
            scalability_results = run_scalability_test()
            print("\\nScalability Test Results:")
            print(json.dumps(scalability_results, indent=2))
            return
        else:  # custom
            config = LoadTestConfig(
                test_duration=args.duration,
                concurrent_users=args.users,
                requests_per_user=args.requests,
                ramp_up_time=args.ramp_up,
                trigger_file=args.trigger_file
            )
            
            tester = WebhookLoadTester(config)
            report = tester.run_load_test()
        
        # Print report
        tester.print_report(report)
        
        # Save report if requested
        if args.output:
            tester.save_report(report, args.output)
    
    except KeyboardInterrupt:
        print("\\nTest interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"Test failed: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()