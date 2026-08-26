(function () {
	'use strict';

	function runTest(ruleRow, testerRow) {
		if (!ruleRow || !testerRow) {
			return;
		}

		var patternInput = ruleRow.querySelector('.regex-blacklist-pattern');
		var sample = testerRow.querySelector('.regex-blacklist-sample');
		var result = testerRow.querySelector('.regex-blacklist-test-result');
		if (!patternInput || !sample || !result) {
			return;
		}

		var pattern = patternInput.value;
		result.classList.remove('regex-blacklist-match', 'regex-blacklist-no-match');

		if (pattern === '') {
			result.textContent = '';
			return;
		}

		try {
			var re = new RegExp(pattern, 'i');
			var matched = re.test(sample.value);
			result.textContent = matched ? '✓ Matches' : '✗ No match';
			result.classList.add(matched ? 'regex-blacklist-match' : 'regex-blacklist-no-match');
		} catch (e) {
			result.textContent = '⚠ Invalid regex: ' + e.message;
		}
	}

	// Persist a running index on the app container itself (rather than a
	// module-level JS variable) so it survives the container being replaced
	// wholesale if the settings panel is closed and reopened.
	function nextRuleIndex(app) {
		var current = parseInt(app.getAttribute('data-next-index') || '', 10);
		if (isNaN(current)) {
			current = app.querySelectorAll('tr.regex-blacklist-rule-row').length;
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
			tmp.content.querySelectorAll('tr').forEach(function (row) {
				rulesBody.appendChild(row);
			});
			return;
		}

		var removeBtn = event.target.closest('.regex-blacklist-remove-btn');
		if (removeBtn) {
			var row = removeBtn.closest('tr.regex-blacklist-rule-row');
			if (row) {
				var testerRow = row.nextElementSibling;
				if (testerRow && testerRow.classList.contains('regex-blacklist-tester-row')) {
					testerRow.remove();
				}
				row.remove();
			}
			return;
		}

		var testBtn = event.target.closest('.regex-blacklist-test-btn');
		if (testBtn) {
			var ruleRow = testBtn.closest('tr.regex-blacklist-rule-row');
			var pairedTesterRow = ruleRow ? ruleRow.nextElementSibling : null;
			if (pairedTesterRow && pairedTesterRow.classList.contains('regex-blacklist-tester-row')) {
				pairedTesterRow.hidden = !pairedTesterRow.hidden;
				if (!pairedTesterRow.hidden) {
					var sample = pairedTesterRow.querySelector('.regex-blacklist-sample');
					if (sample) {
						sample.focus();
					}
					runTest(ruleRow, pairedTesterRow);
				}
			}
		}
	});

	document.addEventListener('input', function (event) {
		if (!event.target.closest('#regexBlacklistApp')) {
			return;
		}

		if (event.target.classList.contains('regex-blacklist-pattern')) {
			var ruleRow = event.target.closest('tr.regex-blacklist-rule-row');
			var testerRow = ruleRow ? ruleRow.nextElementSibling : null;
			runTest(ruleRow, testerRow);
		} else if (event.target.classList.contains('regex-blacklist-sample')) {
			var currentTesterRow = event.target.closest('tr.regex-blacklist-tester-row');
			var currentRuleRow = currentTesterRow ? currentTesterRow.previousElementSibling : null;
			runTest(currentRuleRow, currentTesterRow);
		}
	});
})();
