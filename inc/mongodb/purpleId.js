(function (wp) {
    wp.hooks.addFilter(
        'blocks.registerBlockType',
        'purpleId/idAttribute',
        (settings, name) => {
            settings.attributes = {
                ...settings.attributes,
                purpleId: {
                    type: 'string',
                    default: 'test',
                },
            };
            return settings;
        }
    );
})(window.wp);


