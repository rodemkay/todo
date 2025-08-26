#!/usr/bin/env python3
"""
Project Filter Module for Todo CLI
Handles active project tracking and filtering
"""

import json
import os
from pathlib import Path
from datetime import datetime, timedelta

ACTIVE_PROJECT_FILE = "/tmp/active_project.json"

def get_active_project():
    """
    Get the currently active project ID from the session file
    Returns None if no active project or file doesn't exist
    """
    try:
        if not Path(ACTIVE_PROJECT_FILE).exists():
            return None
            
        with open(ACTIVE_PROJECT_FILE, 'r') as f:
            data = json.load(f)
            
        # Check if data is stale (older than 24 hours)
        if 'timestamp' in data:
            file_time = datetime.fromtimestamp(data['timestamp'])
            if datetime.now() - file_time > timedelta(hours=24):
                print("⚠️  Active project data is stale (>24h old)")
                return None
                
        return data.get('project_id')
        
    except (json.JSONDecodeError, IOError) as e:
        print(f"⚠️  Error reading active project: {e}")
        return None

def set_active_project(project_id, project_name=None, user_id=None):
    """
    Set the active project for CLI operations
    """
    try:
        data = {
            'project_id': project_id,
            'project_name': project_name or f"Project {project_id}",
            'user_id': user_id or 0,
            'timestamp': datetime.now().timestamp()
        }
        
        with open(ACTIVE_PROJECT_FILE, 'w') as f:
            json.dump(data, f, indent=2)
            
        # Make file readable by all users
        os.chmod(ACTIVE_PROJECT_FILE, 0o666)
        
        print(f"✅ Active project set to: {data['project_name']} (ID: {project_id})")
        return True
        
    except IOError as e:
        print(f"❌ Error setting active project: {e}")
        return False

def add_project_filter(query, project_id=None):
    """
    Add project filter to SQL query
    """
    if not project_id:
        project_id = get_active_project()
        
    if not project_id:
        # No active project, return query unchanged
        return query
        
    # Add WHERE clause or extend existing
    if 'WHERE' in query.upper():
        # Find the WHERE clause and add project filter
        query = query.replace('WHERE', f'WHERE project_id = {project_id} AND ', 1)
    else:
        # Add WHERE clause before ORDER BY or LIMIT
        import re
        match = re.search(r'(ORDER BY|LIMIT)', query, re.IGNORECASE)
        if match:
            position = match.start()
            query = query[:position] + f'WHERE project_id = {project_id} ' + query[position:]
        else:
            query += f' WHERE project_id = {project_id}'
            
    return query

def get_project_info():
    """
    Get information about the active project
    """
    try:
        if not Path(ACTIVE_PROJECT_FILE).exists():
            return None
            
        with open(ACTIVE_PROJECT_FILE, 'r') as f:
            data = json.load(f)
            
        return {
            'id': data.get('project_id'),
            'name': data.get('project_name'),
            'user_id': data.get('user_id'),
            'age_hours': round((datetime.now().timestamp() - data.get('timestamp', 0)) / 3600, 1)
        }
        
    except:
        return None

if __name__ == "__main__":
    # Test the module
    info = get_project_info()
    if info:
        print(f"Active Project: {info['name']} (ID: {info['id']})")
        print(f"Set {info['age_hours']} hours ago")
    else:
        print("No active project set")