// import Bricks
import Bricks from "../node_modules/bricks.js/dist/bricks.module.js";

// mq      - the minimum viewport width (String CSS unit: em, px, rem)
// columns - the number of vertical columns
// gutter  - the space (in px) between the columns and grid items

const sizes = [
  { columns: 1, gutter: 24 },
  { mq: "480px", columns: 2, gutter: 24 },
  { mq: "960px", columns: 3, gutter: 32 },
];

// create an instance
const instance = Bricks({
  container: ".collection__items",
  packed: "data-packed",
  sizes: sizes,
  position: false,
});

instance
  .on("pack", () => console.log("ALL grid items packed."))
  .on("update", () => console.log("NEW grid items packed."))
  .on("resize", (size) =>
    console.log("The grid has be re-packed to accommodate a new BREAKPOINT.")
  );

document.addEventListener("DOMContentLoaded", (event) => {
  instance
    .resize(true) // bind resize handler
    .pack(); // pack initial items
});

Drupal.behaviors.mukurtuMasonry = {
  attach: () => {instance.update()},
}
