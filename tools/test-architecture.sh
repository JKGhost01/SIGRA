#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php tests/Unit/ArchitectureTest.php
php tests/Unit/IngestionTest.php
php tests/Unit/ProviderNormalizationTest.php
php tests/Unit/FeedTransportTest.php
php tests/Unit/ProviderHealthTest.php
php tests/Unit/ProviderHeadersTest.php
php tests/Unit/AutomaticRefreshTest.php
php tests/Unit/SensorAlertingTest.php
php tests/CitizenReportLogicTest.php
php tests/ApiResponsesTest.php
php tests/PwaveFusionTest.php
php tests/Feature/ModularPresentationTest.php
node tools/test-map-filters.js
node tools/test-service-worker.js
python3 tests/SetupLocalTest.py
python3 tests/ApiHttpTest.py
python3 tests/CitizenReportHttpTest.py
