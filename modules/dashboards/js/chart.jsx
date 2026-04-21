import Chart from "chart.js/auto";
import interpolate from "color-interpolate";
import "mdn-polyfills/Node.prototype.before";
import "mdn-polyfills/Array.prototype.includes";
const colorMap = require("colormap");

(({ behaviors }, $$, $, { dashboards }) => {
  const colors = colorMap({
    colormap: dashboards.colormap,
    format: "rgb",
    alpha: dashboards.alpha,
    nshades: dashboards.shades
  });

  const fontFamily = getComputedStyle(
    document.documentElement
  ).getPropertyValue("--ginFont");

  behaviors.dashboardsChartTable = {
    attach(context) {
      const charts = $$(context, "[data-app=chart]:not(.processed)");
      charts.forEach(c => {
        c.classList.add("processed");
        const config = {};
        for (let i = 0; i < c.attributes.length; i++) {
          if (c.attributes[i].nodeName.indexOf("data-chart") === 0) {
            config[c.attributes[i].nodeName] = c.attributes[i].nodeValue;
          }
        }
        this.init(c, c.getAttribute("data-chart-type"), config);
      });
    },
    getLabels(t) {
      const labels = [];
      $$(t, "thead th").forEach(v => {
        labels.push(v.textContent);
      });
      return labels;
    },
    getDataSingle(e, index) {
      const t = $(e, "table");
      const dataset = {
        labels: [],
        datasets: []
      };
      const data = [];
      const colormap = interpolate(colors);
      $$(t, "tbody tr td:first-child").forEach(v => {
        dataset.labels.push(v.textContent);
      });
      $$(t, "tbody tr").forEach(v => {
        $$(v, "td:last-child").forEach(td => {
          data.push(td.textContent);
        });
      });

      let max = Math.max.apply(Math, data);

      dataset.datasets.push({
        data: data,
        backgroundColor: [],
        label: $(t, "th:last-child").textContent
      });
      for (var i in dataset.datasets) {
        if (!dataset.datasets[i]) {
          continue;
        }
        dataset.datasets[i].backgroundColor = colormap(
          ((100 / dataset.datasets.length) * i) / 100
        );
        print(dataset.datasets[i].backgroundColor);
      }
      return dataset;
    },
    getData(e, type, index) {
      if (!index) {
        index = 0;
      }
      const tableData = $$(e, "table");
      const t = tableData[index];
      // if ($$(t,'th').length == 2) {
      //   return this.getDataSingle(e);
      // }

      const dataset = {
        labels: $$(t, "tbody tr").map(v => $(v, "td").textContent),
        datasets: []
      };
      const colormap = interpolate(colors);
      let max = 0;

      const rows = $$(t, "th")
        .slice(1)
        .map(v => v.textContent);
      rows.forEach((v, i) => {
        dataset.datasets[i] = {
          data: [],
          label: v
        };
      });
      $$(t, "tbody tr").forEach((v, i) => {
        $$(v, "td")
          .slice(1)
          .forEach((d, ii) => {
            const parsed = parseInt(d.textContent, 10);
            if (!Number.isNaN(parsed)) {
              dataset.datasets[ii].data.push(parsed);
              if (parsed > max) {
                max = parsed;
              }
            } else {
              dataset.datasets[ii].data.push(d.textContent);
            }
          });
      });
      if (dataset.datasets.length === 1) {
        dataset.datasets[0].backgroundColor = [];
        for (var i = 0; i < dataset.datasets[0].data.length; i++) {
          dataset.datasets[0].backgroundColor.push(
            colormap(((100 / dataset.datasets[0].data.length) * i) / 100)
          );
        }
      } else {
        for (var i in dataset.datasets) {
          if (!dataset.datasets[i]) {
            continue;
          }
          dataset.datasets[i].backgroundColor = colormap(
            ((100 / dataset.datasets.length) * i) / 100
          );
        }
      }

      return dataset;
    },
    init(e, type, config) {
      const allowedBars = [
        "bar",
        "pie",
        "line",
        "radar",
        "doughnut",
        "polarArea",
        "bubble",
        "scatter"
      ];
      if (!type || !allowedBars.includes(type)) {
        type = "bar";
      }
      $(e, "div").before(document.createElement("canvas"));
      const canvas = $(e, "canvas");
      const data = this.getData(e, type);
      Chart.defaults.font.family = fontFamily
        ? fontFamily
        : Chart.defaults.font.family;
      e.chart = new Chart(canvas, {
        type: type,
        data: data,
        options: {
          tooltips: {
            mode: "index",
            intersect: false
          },
          hover: {
            mode: "index",
            intersect: false
          },
          legend: {
            display: config["data-chart-display-legend"] ? true : false,
          }
        }
      });
      $(e, "div").style.display = "none";
      const dialog = Drupal.dialog($(e, "table"), {
        width: "80%",
        height: "80%"
      });
      const modalLink = document.createElement("a");
      modalLink.innerHTML = Drupal.t("Show data");
      modalLink.classList.add(
        "button",
        "button--secondary",
        "dashboard-button"
      );
      modalLink.addEventListener("click", e => {
        dialog.show();
      });
      e.appendChild(modalLink);
    }
  };
})(
  Drupal,
  function (e, s) {
    return Array.prototype.slice.call(e.querySelectorAll(s));
  },
  function (e, s) {
    return e.querySelector(s);
  },
  drupalSettings
);
