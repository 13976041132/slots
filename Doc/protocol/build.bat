@echo off

for %%i in (./proto/*.proto) do (
	echo %%i
	"protoc.exe" --proto_path=./proto --php_out=./php %%i
	rem "protoc.exe" --proto_path=./proto --plugin=protoc-gen-lua="plugin\protoc-gen-lua.bat" --lua_out=.\lua %%i
)

php extend.php

del ..\..\Protocol\GPBClass\Enum\*.php
del ..\..\Protocol\GPBClass\Message\*.php
del ..\..\Protocol\GPBMetadata\*.php

move /y .\php\GPBClass\Enum\*.php ..\..\Protocol\GPBClass\Enum
move /y .\php\GPBClass\Message\*.php ..\..\Protocol\GPBClass\Message
move /y .\php\GPBMetadata\*.php ..\..\Protocol\GPBMetadata

export-routes.bat

pause