"use strict";
jQuery(document).ready(function ($) {
    let post = window.detailsSettings.post_fields
    let post_type = window.detailsSettings.post_type
    let post_id = window.detailsSettings.post_id
    /**
     * detect if an update is made on the subassigned field.
     */
    $('#open-subassigned-modal').on('click', ()=>{
        open_reason_subassigned_modal()
    })
    let open_reason_subassigned_modal = ( )=>{
        $('#reason-subassinged-modal').foundation('open');
        if (!window.Typeahead['.js-typeahead-modal_subassigned']) {
            $.typeahead({
                input: '.js-typeahead-modal_subassigned',
                minLength: 0,
                accent: true,
                searchOnFocus: true,
                source: TYPEAHEADS.typeaheadContactsSource(),
                templateValue: "{{name}}",
                template: window.TYPEAHEADS.contactListRowTemplate,
                matcher: function (item) {
                    return !post.subassigned.map(s=>s.ID).includes(parseInt(item.ID));
                },
                dynamic: true,
                hint: true,
                emptyTemplate: window.lodash.escape(window.wpApiShare.translations.no_records_found),
                multiselect: {
                    matchOn: ["ID"],
                    href: window.lodash.escape( window.wpApiShare.site_url ) + "/contacts/{{ID}}"
                },
                callback: {
                    onClick: function (node, a, item) {
                        this.addMultiselectItemLayout(item)
                        event.preventDefault()
                        this.hideLayout();
                        this.resetInput();
                    },
                    onResult: function (node, query, result, resultCount) {
                        let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
                        $('#modal_subassigned-result-container').html(text);
                    },
                    onHideLayout: function () {
                        $('.modal_subassigned-result-container').html("");
                    },
                },
            });
        }
    }

    $('#add-subassigned').on( 'click', ()=>{
        $('#open-subassigned-modal').addClass('loading')
        let typeahead = window.Typeahead['.js-typeahead-modal_subassigned']
        let contact_ids = typeahead.items.map(i=>i.ID)
        let reason = $('#modal-reason-subassinged').val()
        if ( contact_ids.length ){
            let values = contact_ids.map(id=>{
                let v = { value:id }
                if ( reason.length ){
                    v.meta = { reason }
                }
                return v
            })
            API.update_post(post_type, post_id, {subassigned: {values:values}}).then(updated=>{
                post = updated
                display_list_of_subassigned()
                $('#reason-subassinged-modal').foundation('close');
                $('#open-subassigned-modal').removeClass('loading')
                $('#modal-reason-subassinged').val('')
                for (let i = 0; i < typeahead.items.length; i ){
                    typeahead.cancelMultiselectItem(0)
                }
                typeahead.node.trigger('propertychange.typeahead')
            })
        }
    })

    $(document).on( 'click', '.delete-subassigned', function (){
        let subassigned_id = $(this).data('id')
        API.update_post(post.post_type, post.ID, {
            "subassigned": {
                "values": [{
                    "value": subassigned_id,
                    "delete": true
                }]
            }
        }).then(updated=>{
            post = updated
            display_list_of_subassigned()
        })
    })

    let display_list_of_subassigned = ()=>{
        let html = ``
        post.subassigned.forEach( sub =>{
            html += `<div class="connection-item">
                <a  href="${window.lodash.escape(sub.permalink)}">${window.lodash.escape(sub.post_title)}</a>
                <span class="connection-meta">${window.lodash.escape(sub.meta?.reason ? ` (${sub.meta.reason})` : '')}</span>
                <span class="delete-subassigned" data-id="${window.lodash.escape(sub.ID)}">x</span>
            </div>`
        })
        if ( !html.length ){
            html = `<ul class="none-set"><li>No Subassigned</li></ul>`
        }
        $("#list-of-subassigned").html(html)
    }
    display_list_of_subassigned()
})