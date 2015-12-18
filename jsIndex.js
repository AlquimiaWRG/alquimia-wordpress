"use strict";

module.exports = function(defaultJs) {
  defaultJs.getElement('angular').push('./wordpress');
  defaultJs.getElement('modules').push('qWordpress');
  defaultJs.getElement('configs').push(
    "module.constant('SERVER', 'http://localhost/" + alquimia.config.appName.dashed + "/admin/');",
    "module.config(['WPApiProvider', 'SERVER', function(WPApiProvider, SERVER) {",
    "  WPApiProvider.setBaseUrl(SERVER + 'wp-json');",
    "}]);",
    ""
  );

  return defaultJs;
};
