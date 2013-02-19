/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */


;(function (window, $, undefined) {


// jQuery extensions

$.fn.extend({
	twChecked: function (bool) {
		this.attr('checked', !!bool);
		this.trigger('change');
		return this;
	},

	twToggleChecked: function () {
		return this.twChecked(!this.attr('checked'))
	}
});



// nette.ajax extension

$.nette.ext('twigrid', {

	load: function (handler) {

		var self = this;

		$(self.gridSelector).each(function (key, val) {

			// grid parts
			var grid = $(val),
				gForm = grid.find(self.formSelector),
				gHeader = grid.find(self.headerSelector),
					filterSubmit = gHeader.find(self.buttonSelector('[name="' + self.escape('filters[buttons][filter]') + '"]')),
				gBody = grid.find(self.bodySelector),
				gFooter = grid.find(self.footerSelector);


			// filtering
			self.filterBehavior(
				gHeader.find('select[name^="' + self.escape('filters[criteria][') + '"]'),
				filterSubmit
			);

			self.keyboardBehavior(
				gHeader.find(':input:not(' + self.buttonSelector() + ')'),
				filterSubmit
			);


			// inline editing
			var inlines = gBody.find(':input[name^="' + self.escape('inline[values][') + '"]');
			if (inlines.length) {
				self.keyboardBehavior(
					inlines,
					gBody.find(self.buttonSelector('[name="' + self.escape('inline[buttons][edit]') + '"]')),
					gBody.find(self.buttonSelector('[name="' + self.escape('inline[buttons][cancel]') + '"]'))
				);

				inlines.first().trigger('focus');
			}

			self.inlineActivation(gBody.children());


			// rows checkboxes
			var checkboxes = grid.find(':checkbox[name^="' + self.escape('actions[records][') + '"]');
			if (checkboxes.length) {
				self.rowsChecking(
					checkboxes,
					gFooter.find(self.buttonSelector('[name^="' + self.escape('actions[buttons][') + '"]')),
					gHeader
				);
			}


			// client validation
			self.clientValidation(
				gForm,
				grid.find(self.buttonSelector('[data-tw-validate][formnovalidate]'))
			);

			self.confirmationDialog($('*[data-confirm]'));


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
		var els = ['input[type="submit"]', 'input[type="image"]'];
		if (selector) {
			$.each(els, function (i) {
				els[i] = els[i] + selector;
			});
		}

		return els.join(', ');
	},

	filterBehavior: function (selects, submit) {
		selects.off('change.tw-filter')
			.on('change.tw-filter', function (event) {
				submit.trigger('click');
			});
	},

	keyboardBehavior: function (inputs, submit, cancel) {
		inputs.off('focus.tw-keyboard')
			.on('focus.tw-keyboard', function (event) {
				inputs.off('keypress.tw-keyboard')
					.on('keypress.tw-keyboard', function (e) {
						if (e.keyCode === 13 && submit) { // [enter]
							e.preventDefault();
							submit.trigger('click');
						}
					})
					.off('keydown.tw-keyboard')
					.on('keydown.tw-keyboard', function (e) {
						if (e.keyCode === 27 && cancel) { // [esc]
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
	},

	inlineActivation: function (rows) {
		var self = this;
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

	rowsChecking: function (checkboxes, buttons, header) {
		var self = this,
			groupCheckbox = $('<input type="checkbox" />')
			.off('change.tw-rowcheck')
			.on('change.tw-rowcheck', function (event) {
				checkboxes.twChecked(groupCheckbox.attr('checked'));
			});

		header.find('.header-cell')
			.off('click.tw-rowcheck')
			.on('click.tw-rowcheck', function (event) {
				if (!self.isClickable(event.target) && self.noMetaKeysPressed(event)) {
					groupCheckbox.twToggleChecked();
				}
			})
			.first().html(groupCheckbox);

		checkboxes.each(function (k, val) {
			var checkbox = $(val),
				row = checkbox.parent().parent(),
				handler = function () {
					if (checkbox.attr('checked')) {
						row.addClass('info');
						buttons.attr('disabled', false);

					} else {
						row.removeClass('info');
						if (!checkboxes.filter(':checked:first').length) {
							buttons.attr('disabled', true)
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
					if (!self.isClickable(event.target) && self.noMetaKeysPressed(event)) {
						checkbox.twToggleChecked();
					}
				});
		});
	},

	clientValidation: function (form, buttons) {
		var self = this;
		if (window.Nette !== undefined) {
			buttons.off('click.tw-validation')
				.on('click.tw-validation', function (event) {
					form.find(':input[name^="' + self.escape($(this).attr('data-tw-validate')) + '"]')
						.each(function (key, input) {
							if (window.Nette.validateControl(input) === false) {
								event.preventDefault();
								event.stopImmediatePropagation();
								return false; // means break;
							}

							return true;
						});
				});
		}
	},

	confirmationDialog: function (els) {
		els.off('click.tw-confirm')
			.on('click.tw-confirm', function (event) {
				if (!window.confirm($(this).attr('data-confirm'))) {
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

		buttons.each(function (key, val) {
			form.off('click.tw-ajax', buttons.selector)
				.on('click.tw-ajax', buttons.selector, handler);
		});
	},


	// helpers

	escape: function (selector) {
		return selector.replace(/[\!"#\$%&'\(\)\*\+,\.\/:;<=>\?@\[\\\]\^`\{\|\}~]/g, '\\$&');
	},

	isClickable: function (target) {
		return target.nodeName.toUpperCase() in {'A': 1, 'INPUT': 1};
	},

	noMetaKeysPressed: function (event) {
		return !event.ctrlKey && !event.shiftKey && !event.altKey && !event.metaKey;
	},

	onlyCtrlKeyPressed: function (event) {
		return event.ctrlKey && !event.shiftKey && !event.altKey && !event.metaKey;
	}

});


})( window, window.jQuery );
