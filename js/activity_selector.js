var pcnlj_modal = null;

/**
 * Setup the activity selector modal.
 * @param {string} url to be called by the modal (async render)
 */
function pcnlj_setup_modal(url) {
    if (pcnlj_modal !== null) {
        pcnlj_modal.url = url;
        return;
    }

    let container = document.createElement("div");
    container.id = "pcnlj_modal_container";
    document.body.appendChild(container);

    pcnlj_modal = {
        container: container,
        url: url,
        counter: 0
    };
}

/**
 * Show the activity selector modal.
 * @param {number} ref_id of Nolej object
 */
function pcnlj_show_modal(ref_id) {
    if (pcnlj_modal === null) {
        console.log("Modal not found.");
        return;
    }

    if (typeof il === "undefined" || !il.UI || !il.UI.modal) {
        console.log("UI modal not available.");
        return;
    }

    let url = pcnlj_modal.url + "&sel_ref_id=" + encodeURIComponent(ref_id);
    pcnlj_modal.counter++;
    il.UI.modal.showModal(
        pcnlj_modal.container,
        {ajaxRenderUrl: url},
        {id: "pcnlj_modal_" + pcnlj_modal.counter},
        null
    );
}
