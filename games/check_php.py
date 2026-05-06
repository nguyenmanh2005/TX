import re
import os

def check_php_tags(filepath):
    """
    Checks if PHP tags <?php / <?= and ?> are balanced in a file.
    Also detects cases where a closing tag appears before an opening tag on a per-line basis.
    """
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        return [f"ERROR: Could not read file: {e}"]

    issues = []
    
    # 1. Total count check
    opens = re.findall(r'<\?php|<\?=', content)
    closes = re.findall(r'\?>', content)
    
    if len(opens) != len(closes):
        issues.append(f"Mismatched total tags: Opens({len(opens)}) vs Closes({len(closes)})")

    # 2. Sequential balance check (line by line)
    balance = 0
    for i, line in enumerate(content.split('\n'), 1):
        # Ignore tags inside strings or comments (simplistic check)
        # Note: This regex is still basic and might catch tags in strings
        o = len(re.findall(r'<\?php|<\?=', line))
        c = len(re.findall(r'\?>', line))
        
        balance += o - c
        
        if balance < 0:
            issues.append(f"Line {i}: Found closing tag '?>' without matching open tag. Content: {line.strip()}")
            balance = 0 # Reset to continue checking for other errors
            
    # 3. Check for session_start() position
    if "session_start()" in content:
        lines = content.split('\n')
        found_session = False
        for i, line in enumerate(lines, 1):
            if "session_start()" in line:
                # Check if there's any output or non-PHP code before this
                preceding_content = "\n".join(lines[:i-1])
                # Very loose check: if there is any '?>' before session_start, it might be an issue
                if "?>" in preceding_content:
                    issues.append(f"Line {i}: 'session_start()' called after a PHP block was closed. This may cause 'headers already sent' errors.")
                break

    return issues

def main():
    # Use the directory where the script is located
    current_dir = os.path.dirname(os.path.abspath(__file__))
    print(f"--- Scanning directory: {current_dir} ---")
    
    php_files = [f for f in os.listdir(current_dir) if f.endswith('.php')]
    
    if not php_files:
        print("No PHP files found in this directory.")
        return

    total_issues = 0
    files_with_issues = 0

    for file in php_files:
        path = os.path.join(current_dir, file)
        problems = check_php_tags(path)
        
        if problems:
            files_with_issues += 1
            print(f"\n[!] ISSUE FOUND IN: {file}")
            for p in problems:
                print(f"    - {p}")
                total_issues += 1
        else:
            # print(f"[OK] {file}") # Un-comment for verbose output
            pass

    print("\n" + "="*40)
    if total_issues == 0:
        print(f"SUCCESS: All {len(php_files)} PHP files passed the balance check.")
    else:
        print(f"SUMMARY: Found {total_issues} issues across {files_with_issues}/{len(php_files)} files.")
    print("="*40)

if __name__ == "__main__":
    main()
