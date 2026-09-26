(function () {
  var key = 'qdigest-theme';

  function current() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  }

  function paint(theme) {
    document.querySelectorAll('[data-theme-choice]').forEach(function (button) {
      button.setAttribute('aria-pressed', button.getAttribute('data-theme-choice') === theme ? 'true' : 'false');
    });
  }

  function apply(theme) {
    var next = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try {
      localStorage.setItem(key, next);
    } catch (e) {}
    paint(next);
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-theme-choice]');
    if (!button) return;
    apply(button.getAttribute('data-theme-choice'));
  });

  paint(current());
})();
