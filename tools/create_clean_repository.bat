@echo off
echo Creating clean repository without large files...

REM Create backup directory
mkdir backup_original
xcopy /E /I /Q . backup_original\

REM Create new clean directory
mkdir clean_repository
cd clean_repository

REM Initialize new git repository
git init

REM Copy only essential files (excluding uploads, logs, etc.)
xcopy /E /I /Q ..\actions clean_repository\actions\
xcopy /E /I /Q ..\helpers clean_repository\helpers\
xcopy /E /I /Q ..\mobileappapis clean_repository\mobileappapis\
xcopy /E /I /Q ..\models clean_repository\models\
xcopy /E /I /Q ..\schema clean_repository\schema\
xcopy /E /I /Q ..\templates clean_repository\templates\
xcopy /E /I /Q ..\tools clean_repository\tools\
xcopy /E /I /Q ..\UI clean_repository\UI\
xcopy /E /I /Q ..\migrations clean_repository\migrations\

REM Copy important single files
copy ..\index.php clean_repository\
copy ..\config.php clean_repository\
copy ..\composer.json clean_repository\
copy ..\README.md clean_repository\
copy ..\.gitignore clean_repository\

echo Clean repository created in 'clean_repository' directory
echo You can now push this to a new GitHub repository
pause
