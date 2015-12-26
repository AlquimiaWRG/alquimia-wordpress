"use strict";

module.exports = function(defaults) {
  defaults.getElement('angular').push('./wordpress');
  defaults.getElement('modules').push('qWordpress');
  defaults.getElement('configs').push(
    "module.constant('SERVER', '" +
      (alquimia.config.SERVER || ("http://localhost/" + alquimia.config.appName.dashed + "/admin/")) +
      "');",
    "module.config(['WPApiProvider', 'SERVER', function(WPApiProvider, SERVER) {",
    "  WPApiProvider.setBaseUrl(SERVER + 'wp-json');",
    "}]);",
    ""
  );

  return defaults;
};
