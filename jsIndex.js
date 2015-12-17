"use strict";

module.exports = function(defaultJs) {
  defaultJs.getElement('alquimia').wp = true;
  defaultJs.getElement('configs').push(
    "module.constant('SERVER', 'http://localhost/" + alquimia.config.appName.dashed + "/admin/');",
    "module.config(['WPApiProvider', 'SERVER', function(WPApiProvider, SERVER) {",
    "  WPApiProvider.setBaseUrl(SERVER + 'wp-json');",
    "}]);",
    ""
  );

  return defaultJs;
};
