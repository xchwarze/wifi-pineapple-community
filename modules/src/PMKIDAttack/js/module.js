registerController('PMKIDAttack_Dependencies', ['$api', '$scope', '$rootScope', '$interval', function ($api, $scope, $rootScope, $interval) {
    $scope.install = "Loading...";
    $scope.installLabel = "";
    $scope.processing = false;
    $rootScope.pmkid_installedDependencies = false;

    $scope.refreshStatus = function () {
        $rootScope.pmkid_installedDependencies = false;

        $api.request({
            module: "PMKIDAttack",
            action: "getDependenciesStatus"
        }, function (response) {
            $rootScope.pmkid_installedDependencies = response.installed;
            $scope.processing = response.processing;
            $scope.install = response.install;
            $scope.installLabel = response.installLabel;

            if ($scope.processing) {
                $scope.getDependenciesInstallStatus();
            }
        })
    };

    $scope.getDependenciesInstallStatus = function () {
        var dependenciesInstallStatusInterval = $interval(function () {
            $api.request({
                module: 'PMKIDAttack',
                action: 'getDependenciesInstallStatus'
            }, function (response) {
                if (response.success === true) {
                    $scope.processing = false;
                    $scope.refreshStatus();
                    $interval.cancel(dependenciesInstallStatusInterval);
                }
            });
        }, 2000);
    };

    $scope.managerDependencies = function () {
        $scope.install = $rootScope.pmkid_installedDependencies ? "Removing..." : "Installing...";
        $api.request({
            module: 'PMKIDAttack',
            action: 'managerDependencies'
        }, function (response) {
            if (response.success === true) {
                $scope.installLabel = "warning";
                $scope.processing = true;
                $scope.getDependenciesInstallStatus();
            }
        });
    };

    $scope.refreshStatus();
}]);


registerController('PMKIDAttack_ScanLoad', ['$api', '$scope', '$rootScope', '$timeout', function ($api, $scope, $rootScope, $timeout) {
    $scope.scans = [];
    $scope.scanLocation = "";
    $scope.selectedScan = "";
    $scope.loadedScan = null;
    $scope.loadingScan = false;
    $scope.error = false;
    $scope.scanID = null;
    $scope.statusObtained = false;

    // helpers
    $scope.parseScanResults = function (results) {
        annotateMacs();
        var data = results['results'];
        $rootScope.pmkid_accessPoints = data['ap_list'];
        $rootScope.pmkid_unassociatedClients = data['unassociated_clients'];
        $rootScope.pmkid_outOfRangeClients = data['out_of_range_clients'];
    }

    $scope.convertDateToBrowserTime = function(scanDate) {
        var m = [
            "01", "02", "03",
            "04", "05", "06",
            "07", "08", "09",
            "10", "11", "12"
        ];

        var ts = scanDate.replace(' ', 'T');
        ts += 'Z';

        var d = new Date(ts);
        var day = `${d.getDate()}`.padStart(2, '0');
        var year = d.getFullYear();
        var month = d.getMonth();
        var hour = `${d.getHours()}`.padStart(2, '0');
        var mins = `${d.getMinutes()}`.padStart(2, '0');
        var secs = `${d.getSeconds()}`.padStart(2, '0');

        return year + '-' + m[month] + '-' + day + ' ' + hour + ':' + mins + ':' + secs;
    };

    // requests
    $scope.getScans = function() {
        $api.request({
            module: 'Recon',
            action: 'getScans'
        }, function(response) {
            if(response.error === undefined) {
                $scope.scans = response.scans;
                $scope.scans.forEach((scan) => {
                    scan.date = $scope.convertDateToBrowserTime(scan.date);
                });
                $scope.selectedScan = response.scans[0];
                $scope.statusObtained = true;
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.setScanLocation = function() {
        $api.request({
            module: 'Recon',
            action: 'setScanLocation',
            scanLocation: $scope.scanLocation
        }, function(response) {
            if (response.success) {
                $scope.getScanLocation();
                $scope.setLocationSuccess = true;
                $timeout(function () {
                    $scope.setLocationSuccess = false;
                }, 2000);
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.getScanLocation = function() {
        $api.request({
            module: 'Recon',
            action: 'getScanLocation'
        }, function(response) {
            if (response.error === undefined) {
                $scope.scanLocation = response.scanLocation;
                $scope.getScans();
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.displayScan = function() {
        $scope.loadingScan = true;
        $api.request({
            module: 'Recon',
            action: 'loadResults',
            scanID: $scope.selectedScan['scan_id']
        }, function(response) {
            $scope.parseScanResults(response);
            $scope.loadingScan = false;
            $scope.loadedScan = $scope.selectedScan;
            $scope.scanID = $scope.selectedScan['scan_id'];
        });
    };

    $scope.removeScan = function() {
        $api.request({
            module: 'Recon',
            action: 'removeScan',
            scanID: $scope.selectedScan['scan_id']
        }, function(response) {
            if(response.error === undefined) {
                $scope.removedScan = true;
                $scope.loadedScan = null;
                $rootScope.pmkid_accessPoints = [];
                $rootScope.pmkid_unassociatedClients = [];
                $rootScope.pmkid_outOfRangeClients = [];
                $timeout(function() {
                    $scope.removedScan = false;
                }, 2000);
                $scope.getScans();
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.getScanLocation();
}]);


registerController('PMKIDAttack_AttackResults', ['$api', '$scope', '$rootScope', '$interval', function ($api, $scope, $rootScope, $interval) {
    $scope.ssid = '';
    $scope.pmkids = [];
    $scope.pmkidsLoading = false;
    $rootScope.pmkid_bssid = '';
    $rootScope.pmkid_pmkidLog = '';
    $rootScope.pmkid_captureRunning = false;
    $rootScope.pmkid_intervalCheckHash = null;

    $scope.getStatusAttack = function () {
        $api.request({
            action: "getStatusAttack",
            module: "PMKIDAttack"
        }, function (response) {
            if (response.process && response.attack) {
                $scope.ssid = response.ssid;
                $rootScope.pmkid_bssid = response.bssid;
                $scope.checkPMKID();
            } else if (!response.process && response.attack) {
                $rootScope.pmkid_startAttack(response.ssid, response.bssid);
            }
        });
    };

    $scope.checkPMKID = function() {
        $rootScope.pmkid_captureRunning = true;
        if (!$rootScope.pmkid_intervalCheckHash) {
            $rootScope.pmkid_intervalCheckHash = $interval(function () {
                $scope.catchPMKID();
            }, 30000);
        }
    };

    $scope.catchPMKID = function () {
        $api.request({
            action: 'catchPMKID',
            module: 'PMKIDAttack'
        }, function (response) {
            $rootScope.pmkid_pmkidLog = response.pmkidLog;
            if (response.success) {
                $rootScope.pmkid_stopAttack();
            } else if (!response.process) {
                $rootScope.pmkid_startAttack($scope.ssid, $rootScope.pmkid_bssid);
            }
        });
    };

    $scope.getPMKIDFiles = function () {
        $scope.pmkids = [];
        $scope.pmkidsLoading = true;

        $api.request({
            action: 'getPMKIDFiles',
            module: 'PMKIDAttack',
        }, function (response) {
            $scope.pmkids = response.pmkids;
            $scope.pmkidsLoading = false;
        });
    };

    $scope.downloadPMKID = function (file) {
        $api.request({
            action: 'downloadPMKID',
            module: 'PMKIDAttack',
            file: file
        }, function (response) {
            window.location = '/api/?download=' + response.download;
        });
    };

    $scope.deletePMKID = function (file) {
        $api.request({
            action: 'deletePMKID',
            module: 'PMKIDAttack',
            file: file
        }, function (response) {
            $scope.getPMKIDFiles();
        });
    };

    $scope.viewAttackLog = function (file) {
        $rootScope.pmkid_pmkidLog = '';
        $api.request({
            action: 'viewAttackLog',
            module: 'PMKIDAttack',
            file: file
        }, function (response) {
            $rootScope.pmkid_pmkidLog = response.pmkidLog;
        });
    };

    $rootScope.pmkid_startAttack = function (ssid, bssid) {
        $scope.ssid = ssid;
        $rootScope.pmkid_bssid = bssid;
        $rootScope.pmkid_pmkidLog = '';

        $api.request({
            action: 'startAttack',
            module: 'PMKIDAttack',
            ssid: ssid,
            bssid: bssid,
        }, function (response) {
            if (response.success) {
                $scope.checkPMKID();
            }
        });
    };

    $rootScope.pmkid_stopAttack = function () {
        $api.request({
            action: 'stopAttack',
            module: 'PMKIDAttack',
            bssid: $rootScope.pmkid_bssid
        }, function (response) {
            $interval.cancel($rootScope.pmkid_intervalCheckHash);
            delete $rootScope.pmkid_intervalCheckHash;
            $rootScope.pmkid_captureRunning = false;
            $scope.getPMKIDFiles();
        });
    };

    $scope.getPMKIDFiles();
    $scope.getStatusAttack();

    $scope.$on('$destroy', function() {
        $interval.cancel($scope.intervalCheckHash);
    });
}]);


registerController('PMKIDAttack_ScanResults', ['$api', '$scope', '$rootScope', function ($api, $scope, $rootScope) {
    $rootScope.pmkid_accessPoints = [];
    $rootScope.pmkid_unassociatedClients = [];
    $rootScope.pmkid_outOfRangeClients = [];
}]);


registerController('PMKIDAttack_Log', ['$api', '$scope', function ($api, $scope) {
    $scope.moduleLog = '';
    $scope.moduleLogLading = false;

    $scope.refreshLog = function () {
        $scope.moduleLog = '';
        $scope.moduleLogLading = true;

        $api.request({
            module: "PMKIDAttack",
            action: "getLog"
        }, function (response) {
            $scope.moduleLog = response.moduleLog;
            $scope.moduleLogLading = false;
        })
    };

    $scope.clearLog = function () {
        $api.request({
            module: "PMKIDAttack",
            action: "clearLog"
        }, function (response) {
            $scope.moduleLog = '';
        })
    };
}]);
