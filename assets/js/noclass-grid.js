/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

window.NoClassGridInit = function () {
  var nodes = document.querySelectorAll('[data-noclass-grid="1"]');
  nodes.forEach(function (el) {
    var cfgStr = el.getAttribute('data-gridjs') || '{}';
    var cfg = {};
    try { cfg = JSON.parse(cfgStr); } catch (e) { cfg = {}; }
    new gridjs.Grid(cfg).render(el);
  });
};

document.addEventListener('DOMContentLoaded', function () {
  if (window.NoClassGridInit) window.NoClassGridInit();
});