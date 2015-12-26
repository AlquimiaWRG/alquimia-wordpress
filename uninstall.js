"use strict";

module.exports = function(done) {
  delete alquimia.config.SERVER;
  alquimia.del('admin');
  alquimia.del(alquimia.getPath('appDir') + '/' + alquimia.getPath('scriptsDir') + '/wordpress');
  done();
};
