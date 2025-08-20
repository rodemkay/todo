# WP Project Todos - Version 2.0

**Mount-independent WordPress todo management system with SSH-based communication**

## 🏗️ Architecture Overview

This system provides a robust, mount-independent way to manage WordPress todos through direct SSH communication with the remote server. No more dependency on mount points or file system synchronization.

### Key Features

- ✅ **SSH-based Communication** - Direct connection to WordPress server
- ✅ **Mount Independent** - No dependency on file system mounts
- ✅ **Auto-continue Workflow** - Automatically loads next todo after completion
- ✅ **Remote Control** - WordPress admin button integration
- ✅ **Comprehensive Logging** - Detailed logs for debugging and monitoring
- ✅ **Lock Mechanism** - Prevents concurrent operations
- ✅ **Backward Compatibility** - Legacy scripts still work

## 📁 Structure

```
wp-project-todos/
├── config/
│   └── app.json              # Main configuration
├── core/
│   ├── api/
│   │   └── todo_manager.php  # High-level API
│   ├── communication/
│   │   └── ssh_client.php    # SSH communication layer
│   └── triggers/
│       └── watch_daemon.php  # Remote trigger monitoring
├── scripts/
│   ├── load_todo.php         # Load current todo
│   ├── complete_todo.php     # Complete todo
│   ├── block_todo.php        # Block todo
│   ├── check_triggers.php    # Check remote triggers
│   ├── get_stats.php         # Todo statistics
│   ├── sync_plugin.php       # Sync plugin files
│   ├── auto_continue.php     # Auto-continue monitoring
│   └── migration_helper.php  # Migration assistance
├── logs/                     # Log files (auto-created)
├── temp/                     # Temporary files (auto-created)
├── todo*                     # Main command script
└── task_complete.sh*         # Legacy compatibility wrapper
```

## 🚀 Quick Start

### 1. Migration from Old System

```bash
# Run the migration helper to upgrade from mount-based system
php scripts/migration_helper.php
```

### 2. Basic Usage

```bash
# Load current or next todo
./todo

# Complete current todo with notes
./todo complete "Task finished successfully"

# Block current todo with reason
./todo block "Waiting for client approval"

# Show statistics
./todo stats

# Sync plugin files to server
./todo sync

# Check for remote triggers
./todo triggers

# Show help
./todo help
```

### 3. Start Remote Monitoring (Optional)

```bash
# Start the watch daemon for remote control
php core/triggers/watch_daemon.php start

# Check daemon status
php core/triggers/watch_daemon.php status

# Stop daemon
php core/triggers/watch_daemon.php stop
```

## 🔧 Configuration

Edit `config/app.json` to customize:

```json
{
    "server": {
        "host": "159.69.157.54",
        "user": "rodemkay",
        "port": 22,
        "wp_path": "/var/www/forexsignale/staging"
    },
    "database": {
        "name": "staging_forexsignale",
        "prefix": "stage_",
        "table": "project_todos"
    },
    "paths": {
        "trigger_file": "/var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt",
        "response_file": "/var/www/forexsignale/staging/wp-content/uploads/claude_response.json"
    }
}
```

## 🔐 Authentication

SSH credentials are automatically loaded from `.env` file:

```bash
HETZNER_SSH_HOST=159.69.157.54
HETZNER_SSH_USER=rodemkay
HETZNER_SSH_PASS=your_password_here
```

## 📊 Features in Detail

### Auto-Continue Workflow

When you complete a todo, the system automatically:
1. Marks current todo as completed
2. Saves your completion notes
3. Loads the next pending todo
4. Updates status to "in_progress"

### Remote Control Integration

The WordPress admin button:
1. Writes trigger file on server
2. System detects trigger via SSH
3. Loads appropriate todo
4. Sends response back to WordPress

### Comprehensive Logging

- `logs/YYYY-MM-DD.log` - Main application logs
- `logs/auto-continue.log` - Auto-continue monitoring
- All operations are logged with timestamps

### Lock Mechanism

Prevents multiple instances from running simultaneously:
- Automatic lock acquisition
- Graceful cleanup on exit
- Stale lock detection and removal

## 🛠️ Troubleshooting

### SSH Connection Issues

```bash
# Test SSH connection manually
ssh rodemkay@159.69.157.54 "echo 'Connection test'"

# Check SSH configuration
cat ~/.ssh/config

# Verify credentials in .env file
grep HETZNER_SSH ~/.env
```

### Database Access Issues

```bash
# Test database query
./todo stats

# Check WP-CLI access
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SHOW TABLES'"
```

### Plugin Sync Issues

```bash
# Force plugin sync
./todo sync

# Check rsync availability
which rsync

# Manual sync test
rsync -avz --dry-run ./ rodemkay@159.69.157.54:/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos/
```

### Remote Trigger Issues

```bash
# Check trigger file permissions
ssh rodemkay@159.69.157.54 "ls -la /var/www/forexsignale/staging/wp-content/uploads/claude_*"

# Test trigger creation
ssh rodemkay@159.69.157.54 "echo 'test' > /var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt"

# Check trigger detection
./todo triggers
```

## 📈 Monitoring

### Log Analysis

```bash
# Watch real-time logs
tail -f logs/$(date +%Y-%m-%d).log

# Search for errors
grep ERROR logs/*.log

# Check auto-continue activity
tail -f logs/auto-continue.log
```

### Status Monitoring

```bash
# Todo statistics
./todo stats

# Watch daemon status
php core/triggers/watch_daemon.php status

# Check for completed tasks
ls -la temp/TASK_COMPLETED*
```

## 🔄 Workflow Integration

### Claude Code Integration

The system integrates seamlessly with Claude Code CLI:

1. **Todo Loading**: `./todo` command loads current task
2. **Auto-Continue**: Completed tasks automatically trigger next todo
3. **Remote Control**: WordPress admin button works from anywhere
4. **Progress Tracking**: All activities logged and monitored

### tmux Session Integration

```bash
# Start in tmux session
tmux new-session -d -s claude

# Run todo in session
tmux send-keys -t claude './todo' Enter

# Monitor session
tmux attach -t claude
```

## 📋 Migration Notes

### From Mount-Based System

The migration helper automatically:
- ✅ Backs up old scripts
- ✅ Tests SSH connectivity
- ✅ Syncs plugin files
- ✅ Creates compatibility aliases
- ✅ Marks old files as deprecated

### Compatibility

- ✅ `./todo` - New unified command
- ✅ `./task_complete.sh` - Legacy wrapper (still works)
- ✅ All existing workflows preserved
- ✅ WordPress plugin unchanged
- ✅ Database structure unchanged

## 🎯 Performance

- **SSH Connection Pooling**: Reuses connections when possible
- **Minimal Data Transfer**: Only transfers necessary data
- **Efficient Queries**: Optimized database queries
- **Background Monitoring**: Non-blocking trigger detection
- **Graceful Error Handling**: Automatic retry with backoff

## 🔒 Security

- **SSH Key Support**: Supports both password and key authentication
- **Secure Logging**: Passwords never logged
- **File Permissions**: Proper file and directory permissions
- **Process Isolation**: Each operation runs in isolated context
- **Input Validation**: All inputs validated and sanitized

## 💡 Tips & Best Practices

1. **Regular Sync**: Run `./todo sync` after plugin changes
2. **Monitor Logs**: Check logs regularly for issues
3. **Backup Configuration**: Keep `config/app.json` backed up
4. **Use Watch Daemon**: For active remote control usage
5. **Test Connectivity**: Verify SSH access before major operations

## 🆘 Support

For issues or questions:

1. Check the logs in `logs/` directory
2. Run migration helper: `php scripts/migration_helper.php`
3. Test individual components with troubleshooting commands above
4. Review configuration in `config/app.json`

---

**🎉 Enjoy the new mount-independent WP Project Todos system!**