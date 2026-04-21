((Drupal, once) => {
  Drupal.theme.message = (_ref, _ref2) => {
    let {text} = _ref, {type, id} = _ref2;
    const messagesTypes = Drupal.Message.getMessageTypeLabels(), messageWrapper = document.createElement("div");
    return messageWrapper.setAttribute("class", `messages-list__item messages messages--${type}`), 
    messageWrapper.setAttribute("role", "error" === type || "warning" === type ? "alert" : "status"), 
    messageWrapper.setAttribute("data-drupal-message-id", id), messageWrapper.setAttribute("data-drupal-message-type", type), 
    messageWrapper.setAttribute("aria-label", messagesTypes[type]), messageWrapper.innerHTML = `\n    <div class="messages__header">\n      <h2 id="${id}-title" class="messages__title">\n        ${messagesTypes[type]}\n      </h2>\n    </div>\n    <div class="messages__content">\n      ${text}\n    </div>\n    <button type="button" class="button button--dismiss js-message-button-hide" title="${Drupal.t("Hide")}">\n      <span class="icon-close"></span>\n      ${Drupal.t("Hide")}\n    </button>\n  `, 
    Drupal.ginMessages.dismissMessages(messageWrapper), messageWrapper;
  }, Drupal.behaviors.ginMessages = {
    attach: context => {
      Drupal.ginMessages.dismissMessages(context);
    }
  }, Drupal.ginMessages = {
    dismissMessages: function() {
      let context = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : document;
      once("gin-messages-dismiss", ".js-message-button-hide", context).forEach((dismissButton => {
        dismissButton.addEventListener("click", (e => {
          e.preventDefault();
          const message = dismissButton.parentNode;
          message.classList.contains("messages-list__item") && (message.style.opacity = 0, 
          message.classList.add("visually-hidden"));
        }));
      }));
    }
  };
})(Drupal, once);