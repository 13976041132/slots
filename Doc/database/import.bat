@echo off

chcp 65001

php configSync.php env=develop
php configTransform.php env=develop
python37 tocsv.py develop

php import.php env=develop

php configSync.php env=test
php configTransform.php env=develop target=test
python37 tocsv.py test
php import.php local=1 env=develop target=test

pause