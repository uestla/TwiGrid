;(function ($, undefined) {

	// jQuery extensions
	$.fn.checked = function (bool) {
		this.attr('checked', !!bool);
		this.trigger('change');
	};

	$.fn.toggleChecked = function () {
		this.checked( !this.attr('checked') );
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


				// rows checking
				var actionCheckboxes = grid.find(':checkbox[name^="actions\\[records\\]\\["]');

				if (actionCheckboxes.length) {
					var actionButtons = grid.find('input[type="submit"][name^="actions\\[buttons\\]\\["]');

					// toggleCheck all clicking at the header row
					var checkbox = $('<input type="checkbox" />').on('change.twigrid', function (event) {
						actionCheckboxes.checked( $(this).attr('checked') );
					});

					grid.find('table thead tr:first').on('click.twigrid', function (event) {
						// prevent checking when clicking on a link or a checkbox
						var target = $(event.target);
						!target.is('a') && !target.is(':checkbox') && checkbox.toggleChecked();
					}).find('th:first').append( checkbox );

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
							!target.is('a') && !target.is(':checkbox') && checkbox.toggleChecked();
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
