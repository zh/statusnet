/**
 * (c) 2010 StatusNet, Inc.
 */

$(function() {
    /**
     * Find URL links from the source text that may be interesting.
     *
     * @param {String} text
     * @return {Array} list of URLs
     */
    function findLinks(text)
    {
        // @fixme match this to core code
        var re = /(?:^| )(https?:\/\/.+?\/.+?)(?= |$)/mg;
        var links = [];
        var matches;
        while ((matches = re.exec(text)) !== null) {
            links.push(matches[1]);
        }
        return links;
    }

    var oEmbed = {
        api: 'http://oohembed.com/oohembed',
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
                maxwidth: 100,
                maxheight: 75,
                callback: '?'
            };
            $.get(oEmbed.api, params, function(data, xhr) {
                callback(data);
            }, 'jsonp');
        }
    };

    /**
     * Start looking up info for a link preview...
     * May start async data loads.
     *
     * @param {String} id
     * @param {String} url
     */
    function prepLinkPreview(id, url)
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
    }

    /**
     * Update the live preview section with links found in the given text.
     * May start async data loads.
     *
     * @param {String} text: free-form input text
     */
    function previewLinks(text)
    {
        var links = findLinks(text);
        $('#link-preview').html('');
        for (var i = 0; i < links.length; i++) {
            var id = 'link-preview-' + i;
            $('#link-preview').append('<span id="' + id + '"></span>');
            prepLinkPreview(id, links[i]);
        }
    }
    $('#form_notice').append('<div id="link-preview" class="thumbnails"></div>');

    // Piggyback on the counter update...
    var origCounter = SN.U.Counter;
    SN.U.Counter = function(form) {
        previewLinks($('#notice_data-text').val());
        return origCounter(form);
    }
});
