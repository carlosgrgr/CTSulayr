
var promotionBlock = jQuery('#promotion-blog');

function filterPosts(category, promotionBlock) {
    
    var button = jQuery('.btn-load-more');
    jQuery('.cat-list_item').removeClass('c-filter__tag--active');
    jQuery('.cat-list_item[data-slug="' + category + '"]').addClass('c-filter__tag--active');
    var data = {
        action: 'loadmore',
        category: category,
        limit: limit,
    };

    jQuery.ajax({
        url: loadmore_params.ajaxurl,
        dataType: 'html',
        type: 'POST',
        data: data,
        beforeSend: function(xhr) {
            jQuery('.c-post').addClass('c-post--showMore');
        },
        success: function(response) {
            jQuery('.c-post').removeClass('c-post--showMore');
            var response_array = jQuery.parseJSON(response);
            if (response_array["result"] == "ok") {
                jQuery('.latest_posts_wrapper .container .row').html(response_array["content"]);
                page = 1;
                max_pages_latest = response_array["max_pages"];

                if (typeof category === 'undefined' || typeof category === '') {
                    promotionBlock.insertAfter(jQuery('.latest_posts_wrapper .article-blog-ct_sulayr_theme').eq(0));
                }

                if (2 <= response_array.max_pages) {
                    button.show();
                } else {
                    button.hide();
                }

            }
        }
    });
}

jQuery(function($) {
    $('.btn-load-more').click(function(e) {
        e.preventDefault();
            var button = $(this),
            data = {
                action: 'loadmore',
                limit: limit,
                page: page,
                category: jQuery('.c-filter__cobileContent select').find(":selected").data("term-id")
            };

        $.ajax({
            url: loadmore_params.ajaxurl,
			dataType : 'html',
            data: data,
            type: 'POST',
            category: $(this).data('slug'),
            beforeSend: function(xhr) {
                button.text(loadmore_params.text_loading);
                $('.latest_posts_wrapper .loading').addClass('active');
            },
            success: function(response) {
                var response_array = $.parseJSON(response);
                if ( response_array["result"] == "ok" ) {
                    $('.latest_posts_wrapper .container .row').append(response_array["content"]);
                    page++;
                    button.text(loadmore_params.text_show_more);
                    if ( page < max_pages_latest ) { 
                        button.show();
                    } else {
                        button.hide();
                    }
                }
                $('.latest_posts_wrapper .loading').removeClass('active');
            }
        });
        return false;
    });
    
    $('.cat-list_item').on('click', function(e) {
        e.preventDefault();
        filterPosts($(this).data('slug'), promotionBlock);
        return false;
    });

});
