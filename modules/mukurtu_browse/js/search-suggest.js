// Suggests searches under a search box, following the WAI-ARIA combobox
// pattern: focus stays in the box, arrow keys move through the list, Enter
// picks, Escape closes. The server puts the suggestion URL in
// data-mukurtu-search-suggest.
//
// Two modes:
// - words (default): Search API Autocomplete's route suggests words; picking
//   one searches for it.
// - items: the header's route suggests items grouped by content type, each
//   group followed by a link to more of that type, and a final link to all
//   results. Picking any of them opens it.

((Drupal, once) => {
  "use strict";

  const REQUEST_DELAY = 250;
  // Better Exposed Filters skips its auto-submit for inputs with this
  // attribute. It is set while suggestions load or show, so the page doesn't
  // reload under the list.
  const PAUSE_AUTO_SUBMIT = "data-bef-auto-submit-exclude";

  let instanceCount = 0;

  // Drupal.t() escapes placeholders as HTML; this undoes that for textContent.
  function plain(text) {
    return new DOMParser().parseFromString(text, "text/html").documentElement
      .textContent;
  }

  function init(input) {
    const minLength = parseInt(input.dataset.mukurtuSearchSuggestMin, 10) || 1;
    const listId = `mukurtu-search-suggest-${++instanceCount}`;
    const itemsMode = input.dataset.mukurtuSearchSuggestMode === "items";

    // Grouped options need a listbox that can hold groups as well as options.
    const list = document.createElement(itemsMode ? "div" : "ul");
    list.id = listId;
    list.className = "mukurtu-search-suggest__list";
    list.setAttribute("role", "listbox");
    list.setAttribute("aria-label", Drupal.t("Search suggestions"));
    list.hidden = true;
    input.insertAdjacentElement("afterend", list);
    input.parentElement.classList.add("mukurtu-search-suggest");

    input.setAttribute("role", "combobox");
    input.setAttribute("aria-autocomplete", "list");
    input.setAttribute("aria-controls", listId);
    input.setAttribute("aria-expanded", "false");

    let options = [];
    let activeIndex = -1;
    let timer = null;
    let controller = null;
    let resumeOnKeyup = false;

    function setActive(index) {
      options.forEach((option, i) => {
        option.setAttribute("aria-selected", i === index ? "true" : "false");
      });
      activeIndex = index;
      if (index < 0) {
        input.removeAttribute("aria-activedescendant");
        return;
      }
      input.setAttribute("aria-activedescendant", options[index].id);
      options[index].scrollIntoView({ block: "nearest" });
    }

    // Shifts the list sideways if it would run past either edge of the
    // screen, as the wider header list can on small screens.
    function fitToScreen() {
      const gutter = 16;
      const right = document.documentElement.clientWidth - gutter;
      list.style.translate = "";
      const rect = list.getBoundingClientRect();
      let shift = 0;
      if (rect.left < gutter) {
        shift = gutter - rect.left;
      }
      else if (rect.right > right) {
        shift = right - rect.right;
      }
      if (shift) {
        list.style.translate = `${shift}px 0`;
      }
    }

    function open() {
      list.hidden = false;
      input.setAttribute("aria-expanded", "true");
      input.setAttribute(PAUSE_AUTO_SUBMIT, "");
      fitToScreen();
    }

    function close() {
      list.hidden = true;
      input.setAttribute("aria-expanded", "false");
      setActive(-1);
      input.removeAttribute(PAUSE_AUTO_SUBMIT);
    }

    // Better Exposed Filters ignores the keys that close the list, and its
    // timer was held while the list was open. A neutral keyup restarts it, so
    // the search runs on its own as it did before suggestions.
    function resumeAutoSubmit() {
      input.dispatchEvent(new KeyboardEvent("keyup", { bubbles: true }));
    }

    function cancelRequest() {
      clearTimeout(timer);
      if (controller) {
        controller.abort();
        controller = null;
      }
    }

    function submit() {
      const { form } = input;
      if (!form) {
        return;
      }
      const button = form.querySelector(
        "[data-bef-auto-submit-click], [type=submit]",
      );
      form.requestSubmit(button || undefined);
    }

    function pick(option) {
      cancelRequest();
      close();
      if (option.dataset.url) {
        window.location.assign(option.dataset.url);
        return;
      }
      input.value = option.dataset.value;
      submit();
    }

    function addOption(parent, tag, label, modifier) {
      const option = document.createElement(tag);
      option.id = `${listId}-${options.length}`;
      option.className = "mukurtu-search-suggest__option";
      if (modifier) {
        option.classList.add(`mukurtu-search-suggest__option--${modifier}`);
      }
      option.setAttribute("role", "option");
      option.setAttribute("aria-selected", "false");
      option.textContent = label;
      parent.append(option);
      options.push(option);
      return option;
    }

    // Returns how many suggestions were shown.
    function renderWords(data) {
      const words = [...new Set(
        (Array.isArray(data) ? data : [])
          .map((suggestion) => (suggestion.value || "").trim())
          .filter(Boolean),
      )];
      words.forEach((word) => {
        addOption(list, "li", word).dataset.value = word;
      });
      return words.length;
    }

    // Returns how many items were shown, not counting the links.
    function renderItems(data, keys) {
      let count = 0;
      (data.groups || []).forEach((group, g) => {
        const groupList = document.createElement("ul");
        groupList.className = "mukurtu-search-suggest__group";
        groupList.setAttribute("role", "group");
        const heading = document.createElement("li");
        heading.id = `${listId}-group-${g}`;
        heading.className = "mukurtu-search-suggest__heading";
        heading.setAttribute("role", "presentation");
        heading.textContent = group.label;
        groupList.setAttribute("aria-labelledby", heading.id);
        groupList.append(heading);

        group.items.forEach((item) => {
          addOption(groupList, "li", item.label).dataset.url = item.url;
          count += 1;
        });
        if (group.more_url) {
          addOption(groupList, "li", plain(Drupal.t("More @type results (@count)", {
            "@type": group.label,
            "@count": group.count,
          })), "more").dataset.url = group.more_url;
        }
        list.append(groupList);
      });
      if (count && data.all_url) {
        addOption(list, "div", plain(Drupal.t("See all results for “@keys”", {
          "@keys": keys,
        })), "all").dataset.url = data.all_url;
      }
      return count;
    }

    function render(data, keys) {
      list.replaceChildren();
      options = [];
      activeIndex = -1;
      return itemsMode ? renderItems(data || {}, keys) : renderWords(data);
    }

    async function fetchSuggestions(keys) {
      controller = new AbortController();
      const url = new URL(input.dataset.mukurtuSearchSuggest, window.location.origin);
      url.searchParams.set("q", keys);
      let data = null;
      try {
        const response = await fetch(url, {
          signal: controller.signal,
          headers: { Accept: "application/json" },
        });
        if (response.ok) {
          data = await response.json();
        }
      }
      catch (error) {
        if (error.name === "AbortError") {
          return;
        }
      }
      controller = null;
      // The box may have changed or lost focus while the request ran.
      if (input.value.trim() !== keys || document.activeElement !== input) {
        return;
      }

      const count = render(data, keys);
      if (!count) {
        close();
        Drupal.announce(Drupal.t("No suggestions."));
        resumeAutoSubmit();
        return;
      }
      open();
      Drupal.announce(Drupal.formatPlural(
        count,
        "1 suggestion available.",
        "@count suggestions available.",
      ));
    }

    input.addEventListener("input", () => {
      cancelRequest();
      // The highlighted word no longer matches what was typed.
      setActive(-1);
      const keys = input.value.trim();
      if (keys.length < minLength) {
        options = [];
        list.replaceChildren();
        close();
        return;
      }
      input.setAttribute(PAUSE_AUTO_SUBMIT, "");
      timer = setTimeout(() => fetchSuggestions(keys), REQUEST_DELAY);
    });

    input.addEventListener("keydown", (event) => {
      // Keys that build a character in an input method editor, such as Enter
      // confirming a word, belong to the editor, not the list.
      if (event.isComposing || event.keyCode === 229) {
        return;
      }
      const isOpen = !list.hidden;
      switch (event.key) {
        case "ArrowDown":
          if (!options.length) {
            return;
          }
          event.preventDefault();
          if (!isOpen) {
            open();
          }
          setActive(activeIndex + 1 < options.length ? activeIndex + 1 : 0);
          break;

        case "ArrowUp":
          if (!isOpen) {
            return;
          }
          event.preventDefault();
          setActive(activeIndex > 0 ? activeIndex - 1 : options.length - 1);
          break;

        case "Enter":
          if (isOpen && activeIndex >= 0) {
            event.preventDefault();
            pick(options[activeIndex]);
          }
          else if (input.value.trim()) {
            // Mukurtu blocks Enter from submitting forms with several fields,
            // so search for what was typed here.
            event.preventDefault();
            cancelRequest();
            close();
            submit();
          }
          break;

        case "Escape":
          if (isOpen) {
            // Closes only the list, not a menu or dialog around the box.
            event.preventDefault();
            event.stopPropagation();
            cancelRequest();
            close();
            resumeOnKeyup = true;
          }
          else if (input.value) {
            event.preventDefault();
            cancelRequest();
            input.value = "";
            options = [];
          }
          break;

        case "Tab":
          cancelRequest();
          close();
          break;

        default:
          break;
      }
    });

    // Waits for the Escape keyup to pass, so Better Exposed Filters doesn't
    // see that ignored key last and drop the restarted timer.
    input.addEventListener("keyup", (event) => {
      if (resumeOnKeyup && event.key === "Escape") {
        resumeOnKeyup = false;
        setTimeout(resumeAutoSubmit);
      }
    });

    input.addEventListener("blur", () => {
      cancelRequest();
      close();
    });

    // Keeps focus in the box when an option is clicked, so blur doesn't close
    // the list before the click lands.
    list.addEventListener("mousedown", (event) => event.preventDefault());
    list.addEventListener("click", (event) => {
      const option = event.target.closest("[role=option]");
      if (option) {
        pick(option);
      }
    });
  }

  Drupal.behaviors.mukurtuSearchSuggest = {
    attach(context) {
      once("mukurtu-search-suggest", "input[data-mukurtu-search-suggest]", context)
        .forEach(init);
    },
  };
})(Drupal, once);
