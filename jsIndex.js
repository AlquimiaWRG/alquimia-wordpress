"use strict";

module.exports = function(defaultJs) {
  var alquimiaConfig = defaultJs.getElement('alquimia')
  if (alquimiaConfig) alquimiaConfig.wp = true;

  var configs = defaultJs.getElement('configs');

  configs.push(
    "module.constant('SERVER', 'http://localhost/" + alquimia.config.appName.dashed + "/admin/');"
  );

  if (alquimia.config.packages.indexOf('alquimia') >= 0) {
    configs.push(
      "module.config(['WPApiProvider', 'SERVER', function(WPApiProvider, SERVER) {",
      "  WPApiProvider.setBaseUrl(SERVER + 'wp-json');",
      "}]);"
    );
  }

  configs.push("");

  return defaultJs;
};
