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
/*
    // https://stackoverflow.com/questions/105034/how-to-create-a-guid-uuid
    function generateUUID() { // Public Domain/MIT
        var d = new Date().getTime();//Timestamp
        var d2 = (performance && performance.now && (performance.now()*1000)) || 0;//Time in microseconds since page-load or 0 if unsupported
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16;//random number between 0 and 16
            if(d > 0){//Use timestamp until depleted
                r = (d + r)%16 | 0;
                d = Math.floor(d/16);
            } else {//Use microseconds since page-load if supported
                r = (d2 + r)%16 | 0;
                d2 = Math.floor(d2/16);
            }
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }


    function addPurpleId(block) {
        const editorDispatch = wp.data.dispatch('core/block-editor');
        if (block.attributes.purpleId === "test" || block.attributes.purpleId === null) {
            editorDispatch.updateBlockAttributes(block.clientId, {purpleId: generateUUID()});
        }
        block.innerBlocks.forEach( block => addPurpleId(block));
    }

    // generate block id while saving
    wp.hooks.addFilter("blocks.getSaveElement", "purpleId/id", function (
        element,
        block,
        attributes
    ) {
        const blocks = wp.data.select("core/block-editor").getBlocks();
        blocks.forEach(block => {
            addPurpleId(block);
        })

        return element;
    });*/
})(window.wp);


