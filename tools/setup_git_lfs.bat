@echo off
echo Setting up Git LFS for large files...

REM Install Git LFS (you may need to download it first)
git lfs install

REM Track large file types
git lfs track "*.jpg"
git lfs track "*.jpeg"
git lfs track "*.png"
git lfs track "*.gif"
git lfs track "*.pdf"
git lfs track "*.zip"
git lfs track "*.rar"

REM Add .gitattributes file
git add .gitattributes

echo Git LFS setup complete
echo Now commit and push your changes
pause
