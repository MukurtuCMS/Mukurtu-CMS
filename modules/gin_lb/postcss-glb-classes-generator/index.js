const parser = require('postcss-selector-parser');
const fs = require('fs');
const path = require('path');

module.exports = (opts = { }) => {
  return {
    postcssPlugin: 'gin_lb_class_names',
    generate (root, postcss) {
      const ginLbClasses = {}
      const nodeIterator = function(sels) {
        sels.map(function(sel) {
          sel.nodes.forEach((innerNode)=> {

            if (innerNode.type === 'class') {
              if (innerNode.value.indexOf('glb-') === 0) {
                ginLbClasses[innerNode.value] = innerNode.value;
              }
            }
            if (innerNode.type === 'pseudo') {
              innerNode.nodes.forEach((pseudoNode)=> {
                pseudoNode.nodes.forEach((innerPseudoNode)=> {
                  if (innerPseudoNode.value.indexOf('glb-') === 0) {
                    ginLbClasses[innerPseudoNode.value] = innerPseudoNode.value;
                  }
                });
              })
            }
          })
        })
      }
      root.walk((node)=>{
        if (node.selectors != null) {
          return node.selectors.map(function(selector){
            return parser(nodeIterator).process(selector).result
          });
        }
      });
      fs.writeFileSync(opts.targetPath, JSON.stringify(Object.keys(ginLbClasses)));
    },
  }
}
module.exports.postcss = true
