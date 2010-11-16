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

    /**
     * Do an oEmbed lookup for the given URL.
     *
     * @fixme proxy through ourselves if possible?
     * @fixme use the global thumbnail size settings
     *
     * @param {String} url
     * @param {function} callback
     */
    function oEmbedLookup(url, callback)
    {
        var api = 'http://oohembed.com/oohembed';
        var params = {
            url: url,
            format: 'json',
            maxwidth: 100,
            maxheight: 75,
            callback: '?'
        };
        $.get(api, params, function(data, xhr) {
            callback(data);
        }, 'jsonp');
    }

    /**
     * Start looking up info for a link preview...
     * May start async data loads.
     *
     * @param {String} id
     * @param {String} url
     */
    function prepLinkPreview(id, url)
    {
        oEmbedLookup(url, function(data) {
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
                var img = $('<img/>')
                    .attr('src', thumb)
                    .attr('width', width)
                    .attr('title', data.title || data.url || url);
                $('#' + id).append(img);
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
    $('#form_notice').append('<div id="link-preview"></div>');
    $('#notice_data-text').change(function() {
       var text = $(this).val();
       previewLinks(text);
    });
});
