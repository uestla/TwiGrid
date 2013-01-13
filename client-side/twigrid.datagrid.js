/**
 * This file is part of the TwiGrid component
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/twigrid
 */



// jQuery extensions

;(function ($, undefined) {

	$.fn.twg_checked = function (bool) {
		this.attr('checked', !!bool);
		this.trigger('change');
	};

	$.fn.twg_toggleChecked = function () {
		this.twg_checked( !this.attr('checked') );
	};

})( jQuery );



// nette.ajax extension

$.nette.ext('twigrid', {
	init: function () {
		this.body.append('<style>.twigrid-loading * { cursor: wait !important; }</style>');
	},

	load: function () {
		$('.twigrid-cnt').each(function (key, val) {
			var grid = $(val);

			// confirmation dialog
			grid.find('*[data-confirm]').off('click.twigrid').on('click.twigrid', function (event) {
				if (!window.confirm( $(this).attr('data-confirm') )) {
					event.preventDefault();
					event.stopImmediatePropagation();
				}
			});


			// filter controls
			grid.find('select[name^="filters\\[criteria\\]\\["]').off('change.twigrid').on('change.twigrid', function (event) {
				grid.find('input[type="submit"][name="filters\\[buttons\\]\\[filter\\]"]').trigger('click');
			});


			// inline editing
			var inlines = grid.find('[name^="inline\\[values\\]\\["]');
			if (inlines.length) {
				var editButton = grid.find('[name="inline\\[buttons\\]\\[edit\\]"]'),
					cancelButton = grid.find('[name="inline\\[buttons\\]\\[cancel]"]');

				// [Enter] and [Esc] behavior
				inlines.off('focus.twigrid').off('blur.twigrid').on('focus.twigrid', function (event) {
					var single = $(this);
					single.off('keyup.twigrid').on('keyup.twigrid', function (e) {
						if (!e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
							if (e.keyCode === 13) {
								editButton.trigger('click');

							} else if (e.keyCode === 27) {
								cancelButton.trigger('click');
							}
						}
					});

				}).off('blur.twigrid').on('blur.twigrid', function (event) {
					$(this).off('keyup.twigrid');
				});

				// focusing first input comes handy
				inlines.first().trigger('focus');
			}


			// rows checking
			var actionCheckboxes = grid.find(':checkbox[name^="actions\\[records\\]\\["]');

			if (actionCheckboxes.length) {
				var actionButtons = grid.find('input[type="submit"][name^="actions\\[buttons\\]\\["]');

				// toggleCheck all clicking at the header row
				var checkbox = $('<input type="checkbox" />').off('change.twigrid').on('change.twigrid', function (event) {
					actionCheckboxes.twg_checked( $(this).attr('checked') );
				});

				grid.find('table thead tr:first').off('click.twigrid').on('click.twigrid', function (event) {
					// prevent checking when clicking on a link or a checkbox
					var target = $(event.target);
					!target.is('a') && !target.is('input') && checkbox.twg_toggleChecked();
				}).find('th:first').html( checkbox );

				// toggleCheck single record clicking at the record row
				actionCheckboxes.each(function (k, v) {
					var checkbox = $(v),
						row = checkbox.closest('tr'),
						changeListener = function (event) {
							// action buttons disabling + row coloring
							if (checkbox.attr('checked')) {
								row.addClass('info');
								actionButtons.attr('disabled', false);

							} else {
								row.removeClass('info');
								!(actionCheckboxes.filter(':checked').length) && actionButtons.attr('disabled', true);
							}
						};

					changeListener();
					checkbox.off('change.twigrid').on('change.twigrid', changeListener);

					row.off('click.twigrid').on('click.twigrid', function (event) {
						// prevent checking when clicking on a link or a checkbox
						var target = $(event.target);
						!target.is('a') && !target.is('input') && checkbox.twg_toggleChecked();
					});
				});
			}
		});
	},

	before: function () {
		this.body.addClass('twigrid-loading');
	},

	complete: function () {
		this.body.removeClass('twigrid-loading');
	},

	success: function (payload) {
		if (payload.twiGrids) {
			$.each(payload.twiGrids.forms, function (form, action) {
				$('#' + form).attr('action', action);
			});
		}

		var flashes = $('.alert.hidable:first');
		if (flashes.length) {
			var flashOffset = flashes.offset().top, docOffset = $('html').scrollTop() || this.body.scrollTop();
			if (docOffset > flashOffset) {
				$('html, body').animate({
					scrollTop: flashOffset
				}, this.scrollSpeed);
			}
		}
	}

}, {
	body: $(document.body),
	scrollSpeed: 512
});
