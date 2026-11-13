<?php

require __DIR__ . '/bootstrap.php';
expectArchitecture(db_connect()->database === $testDatabase, 'Unexpected migration database');
service('migrations')->setNamespace('App')->latest();
echo "Migrations complete in dedicated test database.\n";
