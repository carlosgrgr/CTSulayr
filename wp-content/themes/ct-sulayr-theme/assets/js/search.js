jQuery(function($) {
    var page = search_params.current_page;
    $('#search-results-wrapper').attr('data-page', page);
    $('#buttonMore').click(function(e) {
        e.preventDefault();
        var button = $(this),
            data = {
                action: 'load_more_search_results',
                query : search_params.posts,
                page  : $('#search-results-wrapper').attr('data-page'),
                nonce : search_params.nonce
            };

        $.ajax({
            url: search_params.ajaxurl,
            data: data,
            type: 'POST',
			dataType : 'html',
            beforeSend: function(xhr) {
                button.text(search_params.text_loading);
            },
            success: function(response) {
                var response_array = $.parseJSON(response);
                if ( response_array["result"] == "ok" ) {
                    $('#search-results-wrapper').append(response_array['content']);

                    page++;
                    $('#search-results-wrapper').attr('data-page', page);
                    button.text(search_params.text_show_more);
                    if ( page < response_array["max_pages"] ) { 
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
    
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.searchForm').forEach((form) => {
        const input = form.querySelector('input[name="s"]');
        const error = form.querySelector('.invalid-feedback');
        const cleanButton = form.querySelector('.c-search__icon-clean');

        if (!input || !error) return;

        // Show/hide clean button
        const toggleCleanButton = () => {
            if (!cleanButton) return;
            cleanButton.classList.toggle('d-none', !input.value.trim());
        };

        // Submit validation
        form.addEventListener('submit', (e) => {
            const isEmpty = !input.value.trim();
            if (isEmpty) {
                e.preventDefault();
                input.classList.add('is-invalid');
                error.classList.remove('d-none');
            } else {
                input.classList.remove('is-invalid');
                error.classList.add('d-none');
            }
        });

        // Change input validation and clean button visibility toggle
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid') && input.value.trim()) {
                input.classList.remove('is-invalid');
                error.classList.add('d-none');
            }
            toggleCleanButton();
        });

        // Clean input
        if (cleanButton) {
            cleanButton.addEventListener('click', (e) => {
                e.preventDefault();
                input.value = '';
                toggleCleanButton();
                input.focus();
            });
        }

        toggleCleanButton();
    });
});