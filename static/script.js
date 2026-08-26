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

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {
		var app = document.getElementById('regexBlacklistApp');
		if (!app) {
			return;
		}

		var rulesBody = document.getElementById('regexBlacklistRulesBody');
		var addBtn = document.getElementById('regexBlacklistAddRule');
		var template = document.getElementById('regexBlacklistRowTemplate');
		var nextIndex = rulesBody ? rulesBody.querySelectorAll('tr.regex-blacklist-rule-row').length : 0;

		if (addBtn && template && rulesBody) {
			addBtn.addEventListener('click', function () {
				var raw = template.innerHTML.split('__INDEX__').join(String(nextIndex));
				var tmp = document.createElement('template');
				tmp.innerHTML = raw;
				tmp.content.querySelectorAll('tr').forEach(function (row) {
					rulesBody.appendChild(row);
				});
				nextIndex++;
			});
		}

		app.addEventListener('click', function (event) {
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

		app.addEventListener('input', function (event) {
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
	});
})();
