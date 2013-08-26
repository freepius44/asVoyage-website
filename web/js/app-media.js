/**
 * Some javascript in bulk for Media module.
 */

/*jslint nomen: true, regexp: true */
/*global document, window, $ */

(function () {
    "use strict";

    /***************************************************************************
     *
     * Manage the selection checkboxes, counters, controls... for media elements.
     *
     **************************************************************************/
    $.media = {

        init: function () {
            this.eventHandlers();
            this.refresh();
        },

        eventHandlers: function () {
            $('.toggle').change($.media.refresh);

            $('.toggle-all').change(function () {
                $.media.toggleCheckboxes();
                $.media.refresh();
            });
        },

        /**
         * Getters, counters, etc.
         */

        // Number of media elements
        count: function () {
            return $('#media').children(':not(.no-element)').length;
        },

        // Number of selected media elements
        countSelected: function () {
            return $('.toggle:checked').length;
        },

        /**
         * Actions on controls, texts, etc.
         */

        refresh: function () {
            $.media.refreshControls();
            $.media.refreshCounters();
        },

        refreshControls: function () {
            $('.control-elements').prop('disabled', $.media.countSelected() === 0);
        },

        refreshCounters: function () {
            $('.counter-elements').html($.media.count());
            $('.counter-selected-elements').html($.media.countSelected());
        },

        toggleCheckboxes: function () {
            $('.toggle').prop(
                'checked',
                $('.toggle-all').is(':checked')
            );
        }
    };


    /***************************************************************************
     *
     * Manage the "set meta" buttons.
     *
     * /!\ For admin purpose.
     *
     **************************************************************************/
    $.mediaMeta = {

        // Ask a value and fill metadata
        handler: function () {
            var $this = this;

            $('.set-meta').click(function (e) {
                e.preventDefault();

                var meta = $(e.currentTarget).data('meta'),
                    message = $this.messages[meta],
                    value = window.prompt(message);

                if (null !== value) {
                    $('.toggle:checked').closest('tr')
                        .find('input[name*="' + meta + '"]')
                        .val(value);
                }
            });
        }
    };


    /***************************************************************************
     *
     * Manage the 3 different ways (short, medium and full)
     * of displaying media elements.
     *
     * /!\ Not for admin purpose.
     *
     **************************************************************************/
    $.mediaViews = {

        init: function () {
            this.handler();
            $('#view-short').click(); // default : short view
        },

        handler: function () {
            // Handle the short view (has tooltip)
            $('#view-short').click(function () {
                $('#media')
                    .removeClass('view-full view-medium')
                    .addClass('view-short');

                $('.thumbnail')
                    .popover('destroy')
                    .tooltip('destroy') // TODO: Imperative to destroy 'tooltip' ! Bug ?
                    .tooltip({
                        html      : true,
                        container : 'body',
                        placement : 'right',
                        title     : function () { return $(this).find('.caption > em').html(); }
                    });
            });

            // Handle the medium view (has popover)
            $('#view-medium').click(function () {
                $('#media')
                    .removeClass('view-full view-short')
                    .addClass('view-medium');

                $.mediaViews.equalizeThumbnails();

                $('.thumbnail')
                    .tooltip('destroy')
                    .popover({
                        html      : true,
                        container : 'body',
                        trigger   : 'hover',
                        title     : function () { return $(this).find('.technical').html(); },
                        content   : function () { return $(this).find('.meta').html(); }
                    });
            });

            // Handle the full view (no tooltip or popover)
            $('#view-full').click(function () {
                $('#media')
                    .removeClass('view-medium view-short')
                    .addClass('view-full');

                $.mediaViews.equalizeThumbnails();

                $('.thumbnail').tooltip('destroy').popover('destroy');
            });
        },

        /**
         * Equalize the heights of left and right '.thumbnail' elements.
         */
        equalizeThumbnails: function () {
            var allMedia = $('#media .thumbnail').height('auto'),
                allLeft  = allMedia.filter(':even'),
                allRight = allMedia.filter(':odd'),
                left,
                right,
                i;

            for (i = 0; i < allRight.length; i += 1) {
                left  = $(allLeft[i]);
                right = $(allRight[i]);

                left.add(right).height(
                    Math.max(left.height(), right.height()) + 5
                );
            }
        }
    };

}());