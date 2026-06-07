@echo off
REM ForgeHub / NoClass — Windows migration convenience script
REM Usage: migrate.bat [status|run|rollback|fresh|make name]
REM
REM Forward slashes work in PHP on Windows — no backslash issues.
php app/system/migrate.php %*
