@echo off
rem Full Docker test run for both extensions. Console output -> test-run-console.log
cd /d "%~dp0"
set "BASH=C:\Program Files\Git\bin\bash.exe"
if not exist "%BASH%" set "BASH=bash"
"%BASH%" docker-test/scripts/run_full_test.sh > test-run-console.log 2>&1
