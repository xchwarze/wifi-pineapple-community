// 2023 - m5kro

// Thanks to xchwarze (DSR) for most of the dependencies code
registerController('ZeroTierDependencies', ['$api', '$scope', '$rootScope', '$interval', '$timeout', function($api, $scope, $rootScope, $interval, $timeout) {
    $scope.install = "Loading...";
    $scope.installLabel = "";
    $scope.processing = false;
    $rootScope.zerotier_installedDependencies = false;
    $rootScope.rebootWhenDone = false;

    $scope.refreshStatus = function () {
        $rootScope.zerotier_installedDependencies = false;

        $api.request({
            module: "ZeroTier",
            action: "getDependenciesStatus"
        }, function (response) {
            $rootScope.zerotier_installedDependencies = response.installed;
            $scope.processing = response.processing;
            $scope.install = response.install;
            $scope.installLabel = response.installLabel;

            if ($scope.processing) {
                $scope.getDependenciesInstallStatus();
            }
        });
    };

    $scope.getDependenciesInstallStatus = function () {
        var dependenciesInstallStatusInterval = $interval(function () {
            $api.request({
                module: "ZeroTier",
                action: "getDependenciesInstallStatus"
            }, function (response) {
                if (response.success === true) {
                    $scope.processing = false;
                    $scope.refreshStatus();
                    $interval.cancel(dependenciesInstallStatusInterval);
                }
            });
        }, 2000);
    };

    $scope.managerDependencies = function (dest) {
        $scope.install = $rootScope.zerotier_installedDependencies ? "Removing..." : "Installing...";
        $api.request({
            module: "ZeroTier",
            action: "managerDependencies",
            where: dest
        }, function (response) {
            if (response.success === true) {
                $scope.installLabel = "warning";
                $scope.processing = true;
                if (dest != "remove"){
                    $rootScope.rebootWhenDone = true;
                }
                $scope.getDependenciesInstallStatus();
            }
        });
    };

    $scope.rebootNow = function () {
        alert("Rebooting!");
        $api.request({
            module: "Configuration",
            action: "rebootPineapple"
        });
    };

    $scope.refreshStatus();

}]);


// Start Stop Zerotier
registerController('ZeroTierController', ['$api', '$scope', '$rootScope', '$interval', '$timeout', function($api, $scope, $rootScope, $interval, $timeout) {

    $scope.running = "Loading...";
    $scope.runningLabel = "";

    $scope.boot = "Loading...";
    $scope.bootLabel = "";

    $scope.identity = "";
    $scope.ip = "";
    $scope.setIDStatus = "";

    $scope.getIdentity = function () {
        $api.request({
            module: "ZeroTier",
            action: "zerotierGetIdentity"
        }, function (response) {
            $scope.identity = response.identity.slice(0,10);
        });
    };

    $scope.refreshZeroTierStatus = function () {
        var dependenciesInstallStatusInterval = $interval(function () {
            $api.request({
                module: "ZeroTier",
                action: "getZeroTierStatus"
            }, function (response) {
                $scope.running = response.running;
                $scope.runningLabel = response.runningLabel;
                $scope.boot = response.boot;
                $scope.bootLabel = response.bootLabel;
                $scope.ip = response.ip;
                $scope.setIDStatus = ""
                $scope.getIdentity();
            });
        }, 2000);
    };

    $scope.zerotierSetID = function () {
        $api.request({
            module: "ZeroTier",
            action: "zerotierSetID",
            ID: $scope.ID
        }, function (response) {
            $scope.setIDStatus = response.confirm;
        });
    };

    $scope.zerotierGetID = function () {
        $api.request({
            module: "ZeroTier",
            action: "zerotierGetID",
        }, function (response) {
            $scope.ID = response.ID;
        });
    };

    $scope.ID = $scope.zerotierGetID();

    $scope.zerotierSwitch = function () {
        $api.request({
            module: "ZeroTier",
            action: "zerotierSwitch"
        });
    };

    $scope.zerotierNewIdentity = function () {
        $api.request({
            module: "ZeroTier",
            action: "zerotierNewIdentity"
        });
    };

    $scope.zerotierBootSwitch = function () {
        $api.request({
            module: "ZeroTier",
            action: "zerotierBootSwitch"
        });
    };

    $scope.refreshZeroTierStatus();
}]);
