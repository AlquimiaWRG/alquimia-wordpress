"use strict";

module.exports = function(done) {
  alquimia.del('admin');
  alquimia.del('app/src/wordpress');
  done();
};
