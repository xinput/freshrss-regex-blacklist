(function () {
	'use strict';

	function runTest(ruleCard) {
		if (!ruleCard) {
			return;
		}

		var patternInput = ruleCard.querySelector('.regex-blacklist-pattern');
		var sample = ruleCard.querySelector('.regex-blacklist-sample');
		var result = ruleCard.querySelector('.regex-blacklist-test-result');
		if (!patternInput || !sample || !result) {
			return;
		}

		var lines = patternInput.value.split('\n')
			.map(function (line) { return line.trim(); })
			.filter(function (line) { return line !== ''; });

		result.classList.remove('regex-blacklist-match', 'regex-blacklist-no-match');

		if (lines.length === 0) {
			result.textContent = '';
			return;
		}

		var invalidCount = 0;
		for (var i = 0; i < lines.length; i++) {
			try {
				var re = new RegExp(lines[i], 'i');
				if (re.test(sample.value)) {
					result.textContent = '✓ Matches (pattern: ' + lines[i] + ')';
					result.classList.add('regex-blacklist-match');
					return;
				}
			} catch (e) {
				invalidCount++;
			}
		}

		if (invalidCount === lines.length) {
			result.textContent = '⚠ All patterns are invalid';
			return;
		}

		result.textContent = '✗ No match' + (invalidCount > 0 ? ' (' + invalidCount + ' invalid pattern(s) skipped)' : '');
		result.classList.add('regex-blacklist-no-match');
	}

	// Persist a running index on the app container itself (rather than a
	// module-level JS variable) so it survives the container being replaced
	// wholesale if the settings panel is closed and reopened.
	function nextRuleIndex(app) {
		var current = parseInt(app.getAttribute('data-next-index') || '', 10);
		if (isNaN(current)) {
			current = app.querySelectorAll('.regex-blacklist-rule').length;
		}
		app.setAttribute('data-next-index', String(current + 1));
		return current;
	}

	// Listeners are delegated on `document`, never bound directly to
	// #regexBlacklistApp's children. FreshRSS can load this settings panel
	// into the page via an AJAX "slider" fetch well after this script has
	// already run once at initial page load, so elements inside it may not
	// exist yet when the script executes — delegation resolves the target
	// at click/input time instead, which works whenever the panel shows up.
	document.addEventListener('click', function (event) {
		var app = event.target.closest('#regexBlacklistApp');
		if (!app) {
			return;
		}

		if (event.target.closest('#regexBlacklistAddRule')) {
			var rulesBody = app.querySelector('#regexBlacklistRulesBody');
			var template = app.querySelector('#regexBlacklistRowTemplate');
			if (!rulesBody || !template) {
				return;
			}
			var index = nextRuleIndex(app);
			var raw = template.innerHTML.split('__INDEX__').join(String(index));
			var tmp = document.createElement('template');
			tmp.innerHTML = raw;
			var card = tmp.content.querySelector('.regex-blacklist-rule');
			if (card) {
				rulesBody.appendChild(card);
				var nameInput = card.querySelector('.regex-blacklist-name');
				if (nameInput) {
					nameInput.focus();
				}
			}
			return;
		}

		var removeBtn = event.target.closest('.regex-blacklist-remove-btn');
		if (removeBtn) {
			var ruleToRemove = removeBtn.closest('.regex-blacklist-rule');
			if (ruleToRemove) {
				ruleToRemove.remove();
			}
			return;
		}

		var testBtn = event.target.closest('.regex-blacklist-test-btn');
		if (testBtn) {
			var ruleCard = testBtn.closest('.regex-blacklist-rule');
			var tester = ruleCard ? ruleCard.querySelector('.regex-blacklist-tester') : null;
			if (tester) {
				tester.hidden = !tester.hidden;
				if (!tester.hidden) {
					var sample = tester.querySelector('.regex-blacklist-sample');
					if (sample) {
						sample.focus();
					}
					runTest(ruleCard);
				}
			}
		}
	});

	document.addEventListener('input', function (event) {
		if (!event.target.closest('#regexBlacklistApp')) {
			return;
		}

		if (event.target.classList.contains('regex-blacklist-pattern') || event.target.classList.contains('regex-blacklist-sample')) {
			runTest(event.target.closest('.regex-blacklist-rule'));
		}
	});
})();
