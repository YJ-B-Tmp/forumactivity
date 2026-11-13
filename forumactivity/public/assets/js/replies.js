// Optional challenge: collapse and expand a comment's child-reply branch.
// Progressive enhancement: replies are visible by default, and the buttons are
// created only after this script runs, so no dead controls appear without JS.
// No comment content is read or written here; only IDs and counts.
(function () {
  'use strict';

  function label(expanded, count) {
    var noun = count === 1 ? 'reply' : 'replies';
    return (expanded ? 'Hide ' : 'Show ') + count + ' ' + noun;
  }

  function init() {
    var comments = document.querySelectorAll('li.comment');
    comments.forEach(function (li) {
      var container = li.querySelector(':scope > ol.replies');
      var slot = li.querySelector(':scope > .comment__card > [data-toggle-slot]');
      if (!container || !slot || !container.id) {
        return; // no children: no button
      }
      var count = parseInt(li.getAttribute('data-reply-count'), 10) || container.children.length;

      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'toggle-replies';
      button.setAttribute('aria-controls', container.id);
      button.setAttribute('aria-expanded', 'true');
      button.textContent = label(true, count);

      button.addEventListener('click', function () {
        var expanded = button.getAttribute('aria-expanded') === 'true';
        container.hidden = expanded;                 // hide or show only this branch
        button.setAttribute('aria-expanded', String(!expanded));
        button.textContent = label(!expanded, count);
      });

      slot.insertBefore(button, slot.firstChild);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
