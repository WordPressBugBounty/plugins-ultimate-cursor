(function ($, elementor) {
  $(window).on("elementor/frontend/init", function () {
    let ModuleHandler = elementorModules.frontend.handlers.Base,CursorEffect;
    let debounce = function (func, wait, immediate) {
      var timeout;
      return function () {
        var context = this,
          args = arguments;
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(function () {
          timeout = null;
          if (!immediate) {
            func.apply(context, args);
          }
        }, wait);
        if (callNow) func.apply(context, args);
      };
    };

    CursorEffect = ModuleHandler.extend({
      bindEvents: function () {
        this.run();
      },
      getDefaultSettings: function () {
        return {};
      },
      onElementChange: debounce(function (prop) {
        if (prop.indexOf("ultimate_cursor_") !== -1) {
          this.run();
        }
      }, 400),

      settings: function (key) {
        return this.getElementSettings("ultimate_cursor_" + key);
      },

      run: function () {
        if (this.settings("show") !== "yes") {
          return;
        }
        var options = this.getDefaultSettings(),
          widgetID = this.$element.data("id"),
          widgetContainer = ".elementor-element-" + widgetID,
          $element = this.$element,
          cursorStyle = this.settings("style");
        const checkClass = $(widgetContainer).find(".uce-cursor-enabled");
        var source = this.settings("source");
        if ($(checkClass).length < 1) {
          if (source === "image") {
            var image = this.settings("image_src.url");
            $(widgetContainer).append(
              '<div class="uce-cursor-enabled"><div id="ultimate-cursor-default-' +
                widgetID +
                '" class="ultimate-cursor-default"><img class="bdt-cursor-image"src="' +
                image +
                '"></div></div>'
            );
          } else if (source === "icons") {
            var svg = this.settings("icons.value.url");
            var icons = this.settings("icons.value");
            if (svg !== undefined) {
              $(widgetContainer).append(
                '<div class="uce-cursor-enabled"><div id="ultimate-cursor-default-' +
                  widgetID +
                  '" class="ultimate-cursor-default"><img class="bdt-cursor-image" src="' +
                  svg +
                  '"></img></div></div>'
              );
            } else {
              $(widgetContainer).append(
                '<div class="uce-cursor-enabled"><div id="ultimate-cursor-default-' +
                  widgetID +
                  '" class="ultimate-cursor-default"><i class="' +
                  icons +
                  ' bdt-cursor-icons"></i></div></div>'
              );
            }
          } else if (source === "text") {
            var text = this.settings("text_label");
            $(widgetContainer).append(
              '<div class="uce-cursor-enabled"><div id="ultimate-cursor-default-' +
                widgetID +
                '" class="ultimate-cursor-default"><span class="bdt-cursor-text">' +
                text +
                "</span></div></div>"
            );
          } else {
            $(widgetContainer).append(
              '<div class="uce-cursor-enabled ' +
                cursorStyle +
                '"><div id="ultimate-cursor-default-' +
                widgetID +
                '" class="ultimate-cursor-default"></div><div id="ultimate-cursor-custom-' +
                widgetID +
                '"  class="ultimate-cursor-custom"></div></div>'
            );
          }
        }
        const cursorBallID =
          "#ultimate-cursor-default-" + this.$element.data("id");
        const cursorBall = document.querySelector(cursorBallID);
        options.models = widgetContainer + " .elementor-widget-container";
        options.speed = 1;
        options.centerMouse = true;
        new Cotton(cursorBall, options);

        if (source === "default") {
          const cursorCircleID =
            "#ultimate-cursor-custom-" + this.$element.data("id");
          const cursorCircle = document.querySelector(cursorCircleID);
          options.models = widgetContainer + " .elementor-widget-container";
          options.speed = this.settings("speed")
            ? this.settings("speed.size")
            : 0.725;
          options.centerMouse = true;
          new Cotton(cursorCircle, options);
        }
      },
    });

    elementorFrontend.hooks.addAction(
      "frontend/element_ready/widget",
      function ($scope) {
        elementorFrontend.elementsHandler.addHandler(CursorEffect, {
          $element: $scope,
        });
      }
    );
  });
})(jQuery, window.elementorFrontend);
