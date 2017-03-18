/**
 * This file is part of the TwiGrid component
 *
 * @license  MIT
 * @author   Petr Kessler (https://kesspess.cz)
 * @link     https://github.com/uestla/twigrid
 */


;(function (window, $, undefined) {


// === jQuery extensions ==========================================

$.fn.extend({

	twgChecked: function (bool) {
		this.each(function () {
			$(this).prop('checked', !!bool)
					.trigger('change');
		});

		return this;
	},

	twgToggleChecked: function () {
		this.each(function () {
			$(this).twgChecked(!$(this).prop('checked'));
		});

		return this;
	},

	twgDisableSelection: function () {
		this.attr('unselectable', 'on')
			.css('user-select', 'none')
			.off('selectstart.twg').on('selectstart.twg', false);

		return this;
	},

	twgClearSelection: function () {
		if (window.getSelection) {
			var selection = window.getSelection();
			if (selection.removeAllRanges) {
				selection.removeAllRanges();
			}

		} else if (window.document.selection) {
			window.document.selection.empty();
		}
	},

	twgEnableSelection: function () {
		this.twgClearSelection();

		this.attr('unselectable', 'off')
			.attr('style', null)
			.off('selectstart.twg');

		return this;
	}

});



// === nette.ajax extension ==========================================

$.nette.ext({

	init: function () {
		var self = this;

		// history API
		if (self.historyAPI) {
			// register initial states of all grids
			$(self.gridSelector).each(function () {
				var grid = $(this);
				self.historyInitialStates[grid.attr('id')] = {
					refreshSignal: grid.attr('data-refresh-signal')
				};
			});

			// refresh grid on popstate
			$(window).on('popstate', function (event) {
				var state = event.originalEvent.state;

				if (!state) { // no state - try to refresh initial state of lastly polluted grid
					if (self.historyLastPolluted && self.historyInitialStates[self.historyLastPolluted]) {
						$.nette.ajax({
							url: self.historyInitialStates[self.historyLastPolluted].refreshSignal
						});
					}

				} else if (state.twiGrid) {
					$.nette.ajax({
						url: state.twiGrid.refreshSignal
					});
				}
			});
		}
	},

	load: function (handler) {
		var self = this;

		$(self.gridSelector).each(function () {
			// grid parts
			var grid = $(this);
			var gForm = $(self.formSelector, grid);
			var gHeader = $(self.headerSelector, grid);
			var filterSubmit = $(self.buttonSelector('[name="filters\[buttons\]\[filter\]"]'), gHeader);
			var gBody = $(self.bodySelector, grid);
			var gFooter = $(self.footerSelector, grid);

			grid.addClass('js');
			self.focusingBehavior();

			// filtering
			self.filterBehavior(
				$(':input:not(' + self.buttonSelector() + ')', gHeader),
				$('select[name^="filters\[criteria\]\["]', gHeader),
				filterSubmit
			);

			// inline editing
			self.inlineEditBehavior(
				$(':input[name^="inline\[values\]\["]', gBody),
				$(self.buttonSelector('[name="inline\[buttons\]\[edit\]"]'), gBody),
				$(self.buttonSelector('[name="inline\[buttons\]\[cancel\]"]'), gBody),
				gBody.children()
			);

			// rows checkboxes
			var checkboxes = self.getGroupActionCheckboxes(grid);
			if (checkboxes.length) {
				self.rowsChecking(
					grid,
					checkboxes,
					$(self.buttonSelector('[name^="actions\[buttons\]\["]'), gFooter),
					gHeader
				);
			}

			// pagination
			self.paginationBehavior(
				grid,
				$('input[name^="pagination\[controls\]\["]', gFooter),
				$(self.buttonSelector('[name="pagination\[buttons\]\[change\]"]'), gFooter)
			);

			// ajaxification
			self.ajaxify(
				$('a.tw-ajax', grid),
				gForm,
				$(self.buttonSelector('.tw-ajax'), grid),
				handler
			);
		});
	},

	before: function (xhr, settings) {
		if (!settings.nette) {
			return ;
		}

		// confirmation dialog
		var question = settings.nette.el.attr('data-tw-confirm');

		if (question) {
			return window.confirm(question);
		}
	},

	start: function (jqXHR, settings) {
		if (this.focusedGrid) {
			this.focusedGrid.addClass(this.loadingClass);

			if (settings.nette && settings.nette.el) {
				$(settings.nette.el, this.focusedGrid).attr('disabled', true).addClass('disabled');
			}
		}
	},

	complete: function (jqXHR, status, settings) {
		if (this.focusedGrid) {
			this.focusedGrid.removeClass(this.loadingClass);

			if (settings.nette && settings.nette.el) {
				$(settings.nette.el, this.focusedGrid).attr('disabled', null).removeClass('disabled');
			}
		}
	},

	success: function (payload) {
		// update form action
		if (payload.twiGrid !== undefined && payload.twiGrid.forms !== undefined) {
			$.each(payload.twiGrid.forms, function (form, action) {
				$('#' + form).attr('action', action);
			});
		}

		// history API - update URL and push state for later refreshing
		if (this.historyAPI && payload.twiGrid && !payload.twiGrid.refreshing) {
			window.history.pushState({
				twiGrid: payload.twiGrid

			}, null, payload.twiGrid.url);

			this.historyLastPolluted = payload.twiGrid.id;
		}

		// scroll to most top flash message
		var $body = $('body');
		var minFlashTop = null;
		var bodyOffsetTop = $body.offset().top + parseInt($body.css('padding-top'));

		$(this.flashSelector, this.gridSelector).each(function () {
			var flash = $(this);
			flash.append($('<button />', {
				type: 'button',
				class: 'close',
				html: '&times;',
				'data-dismiss': 'alert'
			}));

			var flashTop = flash.offset().top - bodyOffsetTop;

			if (minFlashTop === null || flashTop > minFlashTop) {
				minFlashTop = flashTop;
			}
		});

		if (minFlashTop !== null) {
			var windowTop = $(window).scrollTop();

			if (windowTop > minFlashTop) {
				$('html, body').animate({
					scrollTop: minFlashTop

				}, this.scrollSpeed);
			}
		}
	}

}, {
	historyAPI: true,
	historyInitialStates: {},
	historyLastPolluted: null,

	gridSelector: '.tw-cnt',
	formSelector: '.form:first',
	headerSelector: '.header:first',
	bodySelector: '.body:first',
	footerSelector: '.footer:first',

	loadingClass: 'loading',

	flashSelector: '.alert',
	scrollSpeed: 128,

	buttonSelector: function (selector) {
		var els = ['input[type="submit"]', 'button[type="submit"]', 'input[type="image"]'];
		if (selector) {
			$.each(els, function (i) {
				els[i] = els[i] + selector;
			});
		}

		return els.join(', ');
	},

	getGroupActionCheckboxes: function (grid) {
		return $('input[type="checkbox"][name^="actions\[records\]\["]', grid);
	},

	focusingBehavior: function () {
		var self = this;
		var doc = $(window.document);

		doc.off('click.tw-focus')
			.on('click.tw-focus', function (event) {
				self.focusGridFromEvent(event);
			});
	},

	focusGridFromEvent: function (event) {
		var grid = $(event.target).closest(this.gridSelector);
		this.focusedGrid = grid.length ? grid : null;
	},

	filterBehavior: function (inputs, selects, submit) {
		this.submittingBehavior(inputs, submit);

		selects.off('change.tw-filter')
			.on('change.tw-filter', function (event) {
				submit.trigger('click');
			});
	},

	inlineEditBehavior: function (inputs, submit, cancel, rows) {
		var self = this;
		self.submittingBehavior(inputs, submit, cancel);

		if (inputs.length) {
			inputs.first().trigger('focus');
		}

		rows.off('click.tw-inline')
			.on('click.tw-inline', function (event) {
				var row = $(this);
				var edit = $(self.buttonSelector('[name^="inline\[buttons\]\["]:first'), row);

				if (edit.length && !(edit.attr('name') in {'inline[buttons][edit]': 1, 'inline[buttons][cancel]': 1})
						&& !self.isClickable(event.target) && self.onlyCtrlKeyPressed(event)) {
					edit.trigger('click');
				}
			});
	},

	submittingBehavior: function (inputs, submit, cancel) {
		var self = this;

		if (inputs.length) {
			inputs.off('focus.tw-keyboard')
				.on('focus.tw-keyboard', function (event) {
					inputs.off('keydown.tw-keyboard')
						.on('keydown.tw-keyboard', function (e) {
							if ((e.keyCode === 13 || e.keyCode === 10) && submit
									&& (self.isInlineSubmitter(e.target) || self.onlyCtrlKeyPressed(e))) { // [enter]

								e.preventDefault();
								submit.trigger('click');

							} else if (e.keyCode === 27 && cancel) {
								e.preventDefault();
								e.stopImmediatePropagation();
								cancel.trigger('click');
							}
						});

				})
				.off('blur.tw-keyboard')
				.on('blur.tw-keyboard', function (event) {
					inputs.off('keydown.tw-keyboard');
				});
		}
	},

	rowsChecking: function (grid, checkboxes, buttons, header) {
		var self = this;
		var groupCheckbox = $('<input type="checkbox" />')
			.off('change.tw-rowcheck')
			.on('change.tw-rowcheck', function (event) {
				checkboxes.twgChecked(groupCheckbox.prop('checked'));
			});

		$('.header-cell', header)
			.off('click.tw-allrowcheck')
			.on('click.tw-allrowcheck', function (event) {
				if (!self.isClickable(event.target) && self.noMetaKeysPressed(event)) {
					groupCheckbox.twgToggleChecked();
				}
			})
			.first().html(groupCheckbox);

		checkboxes.each(function (k, val) {
			var checkbox = $(val);
			var row = checkbox.parent().parent();
			var handler = function () {
				if (checkbox.prop('checked')) {
					row.addClass('checked');
					buttons.attr('disabled', false);

				} else {
					row.removeClass('checked');
					if (!checkboxes.filter(':checked:first').length) {
						buttons.attr('disabled', true);
					}
				}
			};

			handler();

			checkbox.off('change.tw-rowcheck')
				.on('change.tw-rowcheck', function (event) {
					handler();
				});

			row.off('click.tw-rowcheck')
				.on('click.tw-rowcheck', function (event) {
					if (!self.isClickable(event.target)) {
						if (self.onlyShiftKeyPressed(event)) {
							grid.twgDisableSelection();

							if (self.lastChecked !== null) {
								var checked = checkboxes.eq(self.lastChecked).prop('checked');
								for (var i = 0; i < Math.abs(k - self.lastChecked); i++) {
									checkboxes.eq(Math.abs(k > self.lastChecked ? k - i : k + i))
										.twgChecked(checked);
								}

							} else {
								checkbox.twgToggleChecked();
							}

							grid.twgEnableSelection();

						} else if (self.noMetaKeysPressed(event)) {
							checkbox.twgToggleChecked();
						}

						self.lastChecked = k;
					}
				});
		});
	},

	paginationBehavior: function (grid, input, submit) {
		if (!input.length) {
			return ;
		}

		var self = this;

		input.off('change.tw-pagination')
			.on('change.tw-pagination', function (event) {
				submit.trigger('click');
			});

		self.submittingBehavior(input, submit);

		$(window.document).off('keydown.tw-pagination')
			.on('keydown.tw-pagination', function (event) {
				if (self.focusedGrid !== null && !$(':focus', self.focusedGrid).length
						&& self.onlyCtrlKeyPressed(event) && (event.keyCode === 37 || event.keyCode === 39)) {
					event.preventDefault();

					var max = input.attr('max');
					var actual = parseInt(input.val());

					input.val(Math.max(1, Math.min(max, actual + (event.keyCode === 37 ? -1 : 1))));

					if (actual !== parseInt(input.val())) {
						input.trigger('change');
					}
				}
			});
	},

	ajaxify: function (links, form, buttons, origHandler) {
		var self = this;

		var focusGrid = function (event) {
			self.focusGridFromEvent(event);
		};

		links.off('click.tw-ajax')
			.on('click.tw-ajax', focusGrid)
			.on('click.tw-ajax', origHandler);

		if (form.hasClass('tw-ajax')) {
			form.off('submit.tw-ajax')
				.on('submit.tw-ajax', focusGrid)
				.on('submit.tw-ajax', origHandler)
				.off('click.tw-ajax', this.buttonSelector())
				.on('click.tw-ajax', this.buttonSelector(), focusGrid)
				.on('click.tw-ajax', this.buttonSelector(), origHandler);
		}

		buttons.off('click.tw-ajax')
			.on('click.tw-ajax', focusGrid)
			.on('click.tw-ajax', origHandler);
	},


	// helpers

	focusedGrid: null,
	lastChecked: null, // index of last checked row checkbox

	isClickable: function (target) {
		return target.nodeName.toUpperCase() in {'A': 1, 'INPUT': 1, 'BUTTON': 1, 'TEXTAREA': 1, 'SELECT': 1, 'LABEL': 1};
	},

	isInlineSubmitter: function (target) {
		return !(target.nodeName.toUpperCase() in {'TEXTAREA': 1, 'SELECT': 1});
	},

	noMetaKeysPressed: function (event) {
		return !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;
	},

	onlyCtrlKeyPressed: function (event) {
		return (event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey;
	},

	onlyShiftKeyPressed: function (event) {
		return event.shiftKey && !event.ctrlKey && !event.altKey && !event.metaKey;
	}

});


})(window, window.jQuery);
