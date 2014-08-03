/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013, 2014 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
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

$.nette.ext('twigrid', {

	load: function (handler) {

		var self = this;

		$(self.gridSelector).each(function () {

			// grid parts
			var grid = $(this),
				gForm = grid.find(self.formSelector),
				gHeader = grid.find(self.headerSelector),
					filterSubmit = gHeader.find(self.buttonSelector('[name="' + self.escape('filters[buttons][filter]') + '"]')),
				gBody = grid.find(self.bodySelector),
				gFooter = grid.find(self.footerSelector);


			grid.addClass('js');


			self.focusingBehavior(grid.find(':input'));


			// filtering
			self.filterBehavior(
				gHeader.find(':input:not(' + self.buttonSelector() + ')'),
				gHeader.find('select[name^="' + self.escape('filters[criteria][') + '"]'),
				filterSubmit
			);


			// inline editing
			self.inlineEditBehavior(
				gBody.find(':input[name^="' + self.escape('inline[values][') + '"]'),
				gBody.find(self.buttonSelector('[name="' + self.escape('inline[buttons][edit]') + '"]')),
				gBody.find(self.buttonSelector('[name="' + self.escape('inline[buttons][cancel]') + '"]')),
				gBody.children()
			);


			// rows checkboxes
			var checkboxes = self.getGroupActionCheckboxes(grid);
			if (checkboxes.length) {
				self.rowsChecking(
					grid,
					checkboxes,
					gFooter.find(self.buttonSelector('[name^="' + self.escape('actions[buttons][') + '"]')),
					gHeader
				);
			}


			// pagination
			self.paginationBehavior(
				grid,
				gFooter.find('select[name^="' + self.escape('pagination[controls][') + '"]'),
				gFooter.find(self.buttonSelector('[name="' + self.escape('pagination[buttons][change]') + '"]'))
			);


			self.confirmationDialog($('*[data-tw-confirm]'));


			// ajaxification
			self.ajaxify(
				grid.find('a.tw-ajax'),
				gForm,
				grid.find(self.buttonSelector('.tw-ajax')),
				handler
			);

		});

	},


	success: function (payload) {
		// update form action
		if (payload.twiGrid !== undefined && payload.twiGrid.forms !== undefined) {
			$.each(payload.twiGrid.forms, function (form, action) {
				$('#' + form).attr('action', action);
			});
		}

		// scroll to first flash message
		var flash = $(this.flashSelector);
		if (flash.length) {
			var offset = flash.offset().top,
				docOffset = $('html').scrollTop() || $('body').scrollTop();

			if (docOffset > offset) {
				$('html, body').animate({
					scrollTop: offset
				}, this.scrollSpeed);
			}
		}
	}


}, {

	gridSelector: '.tw-cnt',
	formSelector: '.form:first',
	headerSelector: '.header:first',
	bodySelector: '.body:first',
	footerSelector: '.footer:first',

	flashSelector: '.alert.hidable',
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
		return grid.find('input[type="checkbox"][name^="' + this.escape('actions[records][') + '"]');
	},

	focusingBehavior: function (inputs) {
		var self = this,
			focusedTmp = null;

		if (!self.focusingInitialized) {
			var doc = $(document);

			doc.off('click.tw-focus')
				.on('click.tw-focus', function (event) {
					var target = $(event.target);

					if (!target.is(':input')) {
						var grid = target.closest(self.gridSelector);
						self.focusedGrid = grid.length ? grid : null;
					}
				});

			self.focusingInitialized = true;
		}

		inputs.off('focus.tw-focus')
			.on('focus.tw-focus', function (event) {
				focusedTmp = self.focusedGrid;
				self.focusedGrid = null;
			})
			.off('blur.tw-blur')
			.on('blur.tw-blur', function (event) {
				self.focusedGrid = focusedTmp;
				focusedTmp = null;
			});
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
				var row = $(this),
					edit = row.find(self.buttonSelector('[name^="' + self.escape('inline[buttons][') + '"]:first'));

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

							} else if (cancel && e.keyCode === 27) {
								e.preventDefault();
								e.stopImmediatePropagation();
								cancel.trigger('click');
							}
						});

				})
				.off('blur.tw-keyboard')
				.on('blur.tw-keyboard', function (event) {
					inputs.off('keypress.tw-keyboard')
						.off('keydown.tw-keyboard');
				});
		}
	},

	rowsChecking: function (grid, checkboxes, buttons, header) {
		var self = this,
			groupCheckbox = $('<input type="checkbox" />')
				.off('change.tw-rowcheck')
				.on('change.tw-rowcheck', function (event) {
					checkboxes.twgChecked(groupCheckbox.prop('checked'));
				});

		header.find('.header-cell')
			.off('click.tw-rowcheck')
			.on('click.tw-rowcheck', function (event) {
				if (!self.isClickable(event.target) && self.noMetaKeysPressed(event)) {
					groupCheckbox.twgToggleChecked();
				}
			})
			.first().html(groupCheckbox);

		checkboxes.each(function (k, val) {
			var checkbox = $(val),
				row = checkbox.parent().parent(),
				handler = function () {
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
						grid.twgDisableSelection();

						if (self.onlyShiftKeyPressed(event)) {
							if (self.lastChecked !== null) {
								var checked = checkboxes.eq(self.lastChecked).prop('checked');
								for (var i = 0; i < Math.abs(k - self.lastChecked); i++) {
									checkboxes.eq(Math.abs(k > self.lastChecked ? k - i : k + i))
										.twgChecked(checked);
								}

							} else {
								checkbox.twgToggleChecked();
							}

						} else if (self.noMetaKeysPressed(event)) {
							checkbox.twgToggleChecked();
						}

						self.lastChecked = k;
						grid.twgEnableSelection();
					}
				});
		});
	},

	paginationBehavior: function (grid, selects, submit) {
		if (!selects.length) {
			return ;
		}

		var self = this;

		selects.off('change.tw-pagination')
			.on('change.tw-pagination', function (event) {
				self.getGroupActionCheckboxes(grid).prop('checked', false);
				submit.trigger('click');
			});

		$(document).off('keydown.tw-pagination')
			.on('keydown.tw-pagination', function (event) {
				if (self.focusedGrid !== null
						&& self.onlyCtrlKeyPressed(event) && (event.keyCode === 37 || event.keyCode === 39)) {
					event.preventDefault();

					selects.each(function () {
						var select = $(this),
							selected = select.children(':selected'),
							next = event.keyCode === 37 ? selected.prev() : selected.next();

						if (next.length) {
							select.val(next.val());
							select.trigger('change');
						}
					});
				}
			});
	},

	confirmationDialog: function (els) {
		els.off('click.tw-confirm')
			.on('click.tw-confirm', function (event) {
				if (!window.confirm($(this).attr('data-tw-confirm'))) {
					event.preventDefault();
					event.stopImmediatePropagation();
				}
			});
	},

	ajaxify: function (links, form, buttons, handler) {
		var self = this;
		links.off('click.tw-ajax')
			.on('click.tw-ajax', handler);

		if (form.hasClass('tw-ajax')) {
			form.off('submit.tw-ajax')
				.on('submit.tw-ajax', handler)
				.off('click.tw-ajax', self.buttonSelector())
				.on('click.tw-ajax', self.buttonSelector(), handler);
		}

		form.off('click.tw-ajax', buttons.selector)
			.on('click.tw-ajax', buttons.selector, handler);
	},


	// helpers

	focusedGrid: null,
	focusingInitialized: false,
	lastChecked: null, // index of last checked row checkbox

	escape: function (selector) {
		return selector.replace(/[\!"#\$%&'\(\)\*\+,\.\/:;<=>\?@\[\\\]\^`\{\|\}~]/g, '\\$&');
	},

	isClickable: function (target) {
		return target.nodeName.toUpperCase() in {'A': 1, 'INPUT': 1};
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
