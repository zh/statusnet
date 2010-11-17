/**
 * (c) 2010 StatusNet, Inc.
 */

(function() {
    var oEmbed = {
        api: 'http://oohembed.com/oohembed',
        width: 100,
        height: 75,
        cache: {},
        callbacks: {},

        /**
         * Do a cached oEmbed lookup for the given URL.
         *
         * @param {String} url
         * @param {function} callback
         */
        lookup: function(url, callback)
        {
            if (typeof oEmbed.cache[url] == "object") {
                // We already have a successful lookup.
                callback(oEmbed.cache[url]);
            } else if (typeof oEmbed.callbacks[url] == "undefined") {
                // No lookup yet... Start it!
                oEmbed.callbacks[url] = [callback];

                oEmbed.rawLookup(url, function(data) {
                    oEmbed.cache[url] = data;
                    var callbacks = oEmbed.callbacks[url];
                    oEmbed.callbacks[url] = undefined;
                    for (var i = 0; i < callbacks.length; i++) {
                        callbacks[i](data);
                    }
                });
            } else {
                // A lookup is in progress.
                oEmbed.callbacks[url].push(callback);
            }
        },

        /**
         * Do an oEmbed lookup for the given URL.
         *
         * @fixme proxy through ourselves if possible?
         * @fixme use the global thumbnail size settings
         *
         * @param {String} url
         * @param {function} callback
         */
        rawLookup: function(url, callback)
        {
            var params = {
                url: url,
                format: 'json',
                maxwidth: oEmbed.width,
                maxheight: oEmbed.height,
                token: $('#token').val()
            };
            $.get(oEmbed.api, params, function(data, xhr) {
                callback(data);
            }, 'json');
        }
    };

    var LinkPreview = {
        links: [],

        /**
         * Find URL links from the source text that may be interesting.
         *
         * @param {String} text
         * @return {Array} list of URLs
         */
        findLinks: function (text)
        {
            // @fixme match this to core code
            var re = /(?:^| )(https?:\/\/.+?\/.+?)(?= |$)/mg;
            var links = [];
            var matches;
            while ((matches = re.exec(text)) !== null) {
                links.push(matches[1]);
            }
            return links;
        },

        /**
         * Start looking up info for a link preview...
         * May start async data loads.
         *
         * @param {String} id
         * @param {String} url
         */
        prepLinkPreview: function(id, url)
        {
            oEmbed.lookup(url, function(data) {
                var thumb = null;
                var width = 100;
                if (typeof data.thumbnail_url == "string") {
                    thumb = data.thumbnail_url;
                    if (typeof data.thumbnail_width !== "undefined") {
                        if (data.thumbnail_width < width) {
                            width = data.thumbnail_width;
                        }
                    }
                } else if (data.type == 'photo' && typeof data.url == "string") {
                    thumb = data.url;
                    if (typeof data.width !== "undefined") {
                        if (data.width < width) {
                            width = data.width;
                        }
                    }
                }
                if (thumb) {
                    var link = $('<span class="inline-attachment"><a><img/></a></span>');
                    link.find('a')
                            .attr('href', url)
                            .attr('target', '_blank')
                            .last()
                        .find('img')
                            .attr('src', thumb)
                            .attr('width', width)
                            .attr('title', data.title || data.url || url);
                    $('#' + id).append(link);
                }
            });
        },

        /**
         * Update the live preview section with links found in the given text.
         * May start async data loads.
         *
         * @param {String} text: free-form input text
         */
        previewLinks: function(text)
        {
            var old = LinkPreview.links;
            var links = LinkPreview.findLinks(text);

            // Check for existing common elements...
            for (var i = 0; i < old.length && i < links.length; i++) {
                if (links[i] != old[i]) {
                    // Change an existing entry!
                    var id = 'link-preview-' + i;
                    $('#' + id).html('');
                    LinkPreview.prepLinkPreview(id, links[i]);
                }
            }
            if (links.length > old.length) {
                // Adding new entries, whee!
                for (var i = old.length; i < links.length; i++) {
                    var id = 'link-preview-' + i;
                    $('#link-preview').append('<span id="' + id + '"></span>');
                    LinkPreview.prepLinkPreview(id, links[i]);
                }
            } else if (old.length > links.length) {
                // Remove preview entries for links that have been removed.
                for (var i = links.length; i < old.length; i++) {
                    var id = 'link-preview-' + i;
                    $('#' + id).remove();
                }
            }

            LinkPreview.links = links;
        },

        /**
         * Clear out any link preview data.
         */
        clear: function() {
            LinkPreview.links = [];
            $('#link-preview').empty();
        }
    };

    SN.Init.LinkPreview = function(params) {
        if (params.api) oEmbed.api = params.api;
        if (params.width) oEmbed.width = params.width;
        if (params.height) oEmbed.height = params.height;

        $('#form_notice')
            .append('<div id="link-preview" class="thumbnails"></div>')
            .bind('reset', function() {
                LinkPreview.clear();
            });

        // Piggyback on the counter update...
        var origCounter = SN.U.Counter;
        SN.U.Counter = function(form) {
            LinkPreview.previewLinks($('#notice_data-text').val());
            return origCounter(form);
        }
    }
})();
