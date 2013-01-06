;(function ($, undefined) {

	// jQuery extensions
	$.fn.twg_checked = function (bool) {
		this.attr('checked', !!bool);
		this.trigger('change');
	};

	$.fn.twg_toggleChecked = function () {
		this.twg_checked( !this.attr('checked') );
	};


	// jQuery TwiGrid component
	$.twigrid = {

		init: function (grids) {

			grids.each(function (key, val) {

				var grid = $(val);

				// confirmation dialog
				grid.find('*[data-confirm]').on('click.twigrid', function (event) {
					if (!window.confirm( $(this).attr('data-confirm') )) {
						event.preventDefault();
						event.stopPropagation();
						event.stopImmediatePropagation();
					}
				});


				// filter controls
				grid.find('select[name^="filters\\["]').on('change.twigrid', function (event) {
					grid.find('input[type="submit"][name="filters\\[buttons\\]\\[filter\\]"]').trigger('click');
				});


				// inline editing
				var inlines = grid.find('[name^="inline\\[values\\]\\["]');
				if (inlines.length) {
					var editButton = grid.find('[name="inline\\[buttons\\]\\[edit\\]"]'),
						cancelButton = grid.find('[name="inline\\[buttons\\]\\[cancel]"]');

					// [Enter] and [Esc] behavior
					inlines.on('focus.twigrid', function (event) {
						var single = $(this);
						single.on('keyup.twigrid', function (e) {
							if (!e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
								if (e.keyCode === 13) {
									editButton.trigger('click');

								} else if (e.keyCode === 27) {
									cancelButton.trigger('click');
								}
							}
						});
					}).on('blur.twigrid', function (event) {
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
					var checkbox = $('<input type="checkbox" />').on('change.twigrid', function (event) {
						actionCheckboxes.twg_checked( $(this).attr('checked') );
					});

					grid.find('table thead tr:first').on('click.twigrid', function (event) {
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
						checkbox.on('change.twigrid', changeListener);

						row.on('click.twigrid', function (event) {
							// prevent checking when clicking on a link or a checkbox
							var target = $(event.target);
							!target.is('a') && !target.is('input') && checkbox.twg_toggleChecked();
						});
					});
				}

			});

		}

	};

})( window.jQuery );



$(function () {

	// nette.ajax extensions
	$.nette.ext('twigrid', {
		init: function () {
			$('<style>.loading * { cursor: wait !important; }</style>').appendTo( this.body );

			var snippets;
			$.twigrid.init( $('.twigrid') );
			if (snippets = $.nette.ext('snippets')) {
				snippets.after(function (el) {
					$.twigrid.init( el );

					var flashes = el.find('.alert.hidable'),
						offset;

					if (flashes.length) {
						var maxOffset = -1,
							docOffset = $('html').scrollTop() || $('body').scrollTop();

						flashes.each(function (key, val) {
							if ((offset = $(val).offset().top) > maxOffset) { maxOffset = offset; }
						});

						if (maxOffset > -1 && docOffset > maxOffset) {
							$('html, body').animate({
								scrollTop: maxOffset
							}, 512);
						}
					}
				});
			}
		},

		before: function () {
			this.body.addClass('loading');
		},

		complete: function () {
			this.body.removeClass('loading');
		}
	}, {
		body: $(document.body)
	});

});
