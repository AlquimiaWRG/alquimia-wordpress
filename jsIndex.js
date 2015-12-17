"use strict";

module.exports = function(defaultJs) {
  var alquimiaConfig = defaultJs.getElement('alquimia')
  if (alquimiaConfig) alquimiaConfig.wp = true;

  defaultJs.getElement('configs').push(
    "module.constant('SERVER', 'http://localhost/" + alquimia.config.appName.dashed + "/admin/');",
    "module.config(['WPApiProvider', 'SERVER', function(WPApiProvider, SERVER) {",
    "  WPApiProvider.setBaseUrl(SERVER + 'wp-json');",
    "}]);",
    ""
  );

  return defaultJs;
};
