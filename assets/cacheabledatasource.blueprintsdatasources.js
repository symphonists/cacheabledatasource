(function($, Symphony) {
	'use strict';

	Symphony.Language.add({
		'Caching': false,
		'The cache will automatically be purged when updating entries in the backend.': false,
		'Cache expiration ': false,
		'in minutes': false
	});

	$(document).on('ready.cacheabledatasource', function() {
		var datasource = Symphony.Context.get('cacheabledatasource'),
			fieldset, legend, help, label, input;

		// Create fieldset
		fieldset = $('<fieldset />', {
			class: 'settings contextual'
		}).attr('data-context', 'sections');

		// Create legend
		legend = $('<legend />', {
			text: Symphony.Language.get('Caching')
		}).appendTo(fieldset);

		// Create help
		help = $('<p />', {
			class: 'help',
			text: Symphony.Language.get('The cache will automatically be purged when updating entries in the backend.')
		}).appendTo(fieldset);

		// Create label
		label = $('<label />', {
			html: Symphony.Language.get('Cache expiration') + '<i>' + Symphony.Language.get('in minutes') + '</i>'
		}).appendTo(fieldset);

		// Crate input
		input = $('<input />', {
			name: 'fields[cache]',
			type: 'text',
			value: datasource.cache
		}).appendTo(label);
		
		// Append fieldset
		Symphony.Elements.contents.find('div.actions').before(fieldset);
	});

})(window.jQuery, window.Symphony);
