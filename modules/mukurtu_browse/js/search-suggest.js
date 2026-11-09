// Suggests search words under a search box, following the WAI-ARIA combobox
// pattern: focus stays in the box, arrow keys move through the list, Enter
// picks, Escape closes. Suggestions come from Search API Autocomplete's route,
// whose URL the server puts in data-mukurtu-search-suggest.

((Drupal, once) => {
  "use strict";

  const REQUEST_DELAY = 250;
  // Better Exposed Filters skips its auto-submit for inputs with this
  // attribute. It is set while suggestions load or show, so the page doesn't
  // reload under the list.
  const PAUSE_AUTO_SUBMIT = "data-bef-auto-submit-exclude";

  let instanceCount = 0;

  function init(input) {
    const minLength = parseInt(input.dataset.mukurtuSearchSuggestMin, 10) || 1;
    const listId = `mukurtu-search-suggest-${++instanceCount}`;

    const list = document.createElement("ul");
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
      input.value = option.dataset.value;
      cancelRequest();
      close();
      submit();
    }

    function render(words) {
      list.replaceChildren();
      options = words.map((word, i) => {
        const option = document.createElement("li");
        option.id = `${listId}-${i}`;
        option.className = "mukurtu-search-suggest__option";
        option.setAttribute("role", "option");
        option.setAttribute("aria-selected", "false");
        option.dataset.value = word;
        option.textContent = word;
        list.append(option);
        return option;
      });
      activeIndex = -1;
    }

    async function fetchSuggestions(keys) {
      controller = new AbortController();
      const url = new URL(input.dataset.mukurtuSearchSuggest, window.location.origin);
      url.searchParams.set("q", keys);
      let words = [];
      try {
        const response = await fetch(url, {
          signal: controller.signal,
          headers: { Accept: "application/json" },
        });
        if (response.ok) {
          const data = await response.json();
          words = [...new Set(
            data.map((suggestion) => (suggestion.value || "").trim()).filter(Boolean),
          )];
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

      render(words);
      if (!words.length) {
        close();
        Drupal.announce(Drupal.t("No suggestions."));
        resumeAutoSubmit();
        return;
      }
      list.hidden = false;
      input.setAttribute("aria-expanded", "true");
      Drupal.announce(Drupal.formatPlural(
        words.length,
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
      const open = !list.hidden;
      switch (event.key) {
        case "ArrowDown":
          if (!options.length) {
            return;
          }
          event.preventDefault();
          if (!open) {
            list.hidden = false;
            input.setAttribute("aria-expanded", "true");
            input.setAttribute(PAUSE_AUTO_SUBMIT, "");
          }
          setActive(activeIndex + 1 < options.length ? activeIndex + 1 : 0);
          break;

        case "ArrowUp":
          if (!open) {
            return;
          }
          event.preventDefault();
          setActive(activeIndex > 0 ? activeIndex - 1 : options.length - 1);
          break;

        case "Enter":
          if (open && activeIndex >= 0) {
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
          if (open) {
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
