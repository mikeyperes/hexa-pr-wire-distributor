(function () {
    "use strict";

    var boxId = "imageUrlMetaBox";
    var initialized = false;
    var toggleToken = 0;

    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }

        callback();
    }

    function getBox() {
        var box = document.getElementById(boxId);

        return box && box.classList.contains("postbox") ? box : null;
    }

    function getInside(box) {
        return box.querySelector(":scope > .inside") || box.querySelector(".inside");
    }

    function setAria(box, expanded) {
        var buttons = box.querySelectorAll(".handlediv, .postbox-header button[aria-expanded]");

        Array.prototype.forEach.call(buttons, function (button) {
            button.setAttribute("aria-expanded", expanded ? "true" : "false");
        });
    }

    function applyState(box, expanded) {
        var inside = getInside(box);

        box.classList.toggle("closed", !expanded);

        if (inside) {
            if (expanded) {
                inside.style.removeProperty("display");
                if (inside.getAttribute("style") === "") {
                    inside.removeAttribute("style");
                }
            } else {
                inside.style.display = "none";
            }
        }

        setAria(box, expanded);
    }

    function scheduleState(expanded) {
        var token = ++toggleToken;

        [0, 40, 160, 420, 1200, 2800].forEach(function (delay) {
            window.setTimeout(function () {
                var box = getBox();
                if (box && token === toggleToken) {
                    applyState(box, expanded);
                }
            }, delay);
        });
    }

    function eventBox(event) {
        if (!event.target || !event.target.closest) {
            return null;
        }

        var selector = "#" + boxId + " .handlediv, #" + boxId + " .hndle, #" + boxId + " .postbox-header, #" + boxId + " button[aria-expanded]";

        return event.target.closest(selector) ? getBox() : null;
    }

    function toggle(event) {
        var box = eventBox(event);
        if (!box) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        scheduleState(box.classList.contains("closed"));
    }

    function ensureInitialState() {
        var box = getBox();
        if (!box) {
            return;
        }

        box.dataset.hprFifuPostboxToggle = "4";

        if (!initialized) {
            initialized = true;
            applyState(box, false);
        }
    }

    function bindEvents() {
        if (document.documentElement.dataset.hprFifuPostboxToggle === "4") {
            return;
        }

        document.documentElement.dataset.hprFifuPostboxToggle = "4";

        document.addEventListener("click", toggle, true);
        document.addEventListener("keydown", function (event) {
            if (event.key === "Enter" || event.key === " ") {
                toggle(event);
            }
        }, true);
    }

    ready(function () {
        bindEvents();
        ensureInitialState();

        [50, 300, 1000, 2500].forEach(function (delay) {
            window.setTimeout(ensureInitialState, delay);
        });

        if (window.MutationObserver && document.body) {
            new MutationObserver(ensureInitialState).observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
}());
