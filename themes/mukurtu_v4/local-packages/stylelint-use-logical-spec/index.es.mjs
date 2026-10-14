import stylelint from 'stylelint';

const inline = {
  start: {
    ltr: 'left',
    rtl: 'right'
  },
  end: {
    ltr: 'right',
    rtl: 'left'
  }
};
const propsThatContainPropsInValue = /^(transition(-property)?|will-change)$/i;
const physical4Prop = [[['top', 'left', 'bottom', 'right'], 'inset'], [['margin-top', 'margin-left', 'margin-bottom', 'margin-right'], 'margin'], [['padding-top', 'padding-left', 'padding-bottom', 'padding-right'], 'padding']];
const physicalShorthandProp = ['inset', 'margin', 'padding'];
const physical2Prop = dir => [[['top', 'bottom'], 'inset-block'], [[inline.start[dir], inline.end[dir]], 'inset-inline'], [['margin-top', 'margin-bottom'], 'margin-block'], [[`margin-${inline.start[dir]}`, `margin-${inline.end[dir]}`], 'margin-inline'], [['padding-top', 'padding-bottom'], 'padding-block'], [[`padding-${inline.start[dir]}`, `padding-${inline.end[dir]}`], 'padding-inline']];
const physicalProp = dir => [[['top'], 'inset-block-start'], [['bottom'], 'inset-block-end'], [[inline.start[dir]], 'inset-inline-start'], [[inline.end[dir]], 'inset-inline-end'], [['margin-top'], 'margin-block-start'], [['margin-bottom'], 'margin-block-end'], [[`margin-${inline.start[dir]}`], 'margin-inline-start'], [[`margin-${inline.end[dir]}`], 'margin-inline-end'], [['padding-top'], 'padding-block-start'], [['padding-bottom'], 'padding-block-end'], [[`padding-${inline.start[dir]}`], 'padding-inline-start'], [[`padding-${inline.end[dir]}`], 'padding-inline-end'], // border
[['border-top'], 'border-block-start'], [['border-bottom'], 'border-block-end'], [[`border-${inline.start[dir]}`], 'border-inline-start'], [[`border-${inline.end[dir]}`], 'border-inline-end'], [['border-top-color'], 'border-block-start-color'], [['border-top-style'], 'border-block-start-style'], [['border-top-width'], 'border-block-start-width'], [['border-bottom-color'], 'border-block-end-color'], [['border-bottom-style'], 'border-block-end-style'], [['border-bottom-width'], 'border-block-end-width'], [[`border-${inline.start[dir]}-color`], 'border-inline-start-color'], [[`border-${inline.start[dir]}-style`], 'border-inline-start-style'], [[`border-${inline.start[dir]}-width`], 'border-inline-start-width'], [[`border-${inline.end[dir]}-color`], 'border-inline-end-color'], [[`border-${inline.end[dir]}-style`], 'border-inline-end-style'], [[`border-${inline.end[dir]}-width`], 'border-inline-end-width'], [[`border-top-${inline.start[dir]}-radius`], 'border-start-start-radius'], [[`border-bottom-${inline.start[dir]}-radius`], 'border-end-start-radius'], [[`border-top-${inline.end[dir]}-radius`], 'border-start-end-radius'], [[`border-bottom-${inline.end[dir]}-radius`], 'border-end-end-radius'], [['height'], 'block-size'], [['min-height'], 'min-block-size'], [['max-height'], 'max-block-size'], [['width'], 'inline-size'], [['min-width'], 'min-inline-size'], [['max-width'], 'max-inline-size']];
const physicalValue = dir => [[/^clear$/i, {
  [inline.start[dir]]: 'inline-start',
  [inline.end[dir]]: 'inline-end'
}], [/^float$/i, {
  [inline.start[dir]]: 'inline-start',
  [inline.end[dir]]: 'inline-end'
}], [/^text-align$/i, {
  [inline.start[dir]]: 'start',
  [inline.end[dir]]: 'end'
}]];
const migrationNoneSpec = [[['margin-start'], ['margin-block-start', 'margin-inline-start']], [['margin-end'], ['margin-block-end', 'margin-inline-end']], [['padding-start'], ['padding-block-start', 'padding-inline-start']], [['padding-end'], ['padding-block-end', 'padding-inline-end']], [['inset-start'], ['inset-inline-start', 'inset-block-start']], [['inset-end'], ['inset-inline-end', 'inset-block-end']]];

const validateRuleWithProps = (root, props, fn) => {
  // For supporting css-in-js
  const {
    nodes = [root]
  } = root; // conditionally walk nodes with children

  if (nodes && nodes.length) {
    const args = [];
    const hasProps = props.every(prop => {
      const declIndex = nodes.findIndex(child => child.type === 'decl' && child.prop === prop);
      const decl = nodes[declIndex];

      if (decl) {
        args.push(decl, declIndex);
      }

      return decl;
    });

    if (hasProps) {
      fn(...args);
    }
  }
};

const cssValueSplit = value => {
  const ret = [];
  const stack = [];
  let part = '';
  let esc = false;
  let q = '';

  for (let i = 0; i < value.length; i++) {
    const c = value[i];

    if (esc) {
      esc = false;
    } else if (c === '\\') {
      esc = true;
    } else if (q) {
      if (c === q) {
        q = '';
      }
    } else if (c === '\'' || c === '"') {
      q = c;
    } else if (c === ' ' && stack.length === 0) {
      if (part) {
        ret.push(part);
      }

      part = '';
      continue;
    } else if (c === '(') {
      stack.push(')');
    } else if (c === '{') {
      stack.push('}');
    } else if (c === '[') {
      stack.push(']');
    } else if (stack.length && c === stack[stack.length - 1]) {
      stack.length--;
    }

    part += c;
  }

  if (part) {
    ret.push(part);
  }

  return ret;
}; // console.log(cssValueSplit('3px  0 5px'));
// -> [ '3px', '0', '5px' ]
// console.log(cssValueSplit('3px calc(--bla, 0) 5px'));
// -> [ '3px', 'calc(--bla, 0)', '5px' ]
// console.log(cssValueSplit('3px map-get($spacers, 2) 5px'));
// -> [ '3px', 'map-get($spacers, 2)', '5px' ]
// console.log(cssValueSplit('-#{map-get($spacers, 2)} #{map-get($spacers, 3)} 5px'));
// -> [ '-#{map-get($spacers, 2)}', '#{map-get($spacers, 3)}', '5px' ]
// console.log(cssValueSplit('-#{function("something ) else\')\\" bla", 2)} #{map-get($spacers, 3)} 5px'));
// [ `-#{function("something ) else')\\" bla", 2)}`, '#{map-get($spacers, 3)}', '5px' ]

var ruleName = 'liberty/use-logical-spec';

var messages = stylelint.utils.ruleMessages(ruleName, {
  unexpectedProp(physicalProperty, logicalProperty) {
    return `Unexpected "${physicalProperty}" property. Use "${logicalProperty}".`;
  },

  unexpectedValue(property, physicalValue, logicalValue) {
    return `Unexpected "${physicalValue}" value in "${property}" property. Use "${logicalValue}".`;
  },

  unsupportedProp(physicalProperty, logicalProperty) {
    return `"${physicalProperty}" is not part of CSS specs. Use "${logicalProperty[0]}" and "${logicalProperty[1]}".`;
  }

});

// walk all container nodes
function walk(node, fn) {
  if (node.nodes && node.nodes.length) {
    const nodes = node.nodes.slice();
    const length = nodes.length;
    let index = -1;

    while (++index < length) {
      const child = nodes[index];

      if (!isDirRule(child)) {
        fn(child);
        walk(child, fn);
      }
    }
  }
}
const dirSelectorRegExp = /:dir\(ltr|rtl\)/i;

const isDirRule = node => node.type === 'rule' && dirSelectorRegExp.test(node.selector);

const reportedDecls = new WeakMap(); // Ignore autofix on those expression value

const expressionRegex = /^\$\{.*\}$/g;

function ruleFunc(method, opts, context) {
  const propExceptions = [].concat(Object(opts).except || []);

  const isAutofixable = node => isContextAutofixing(context) && !expressionRegex.test(node.value);

  const dir = /^rtl$/i.test(Object(opts).direction) ? 'rtl' : 'ltr';
  return (root, result) => {
    // validate the method
    const isMethodValid = stylelint.utils.validateOptions(result, ruleName, {
      actual: method,

      possible() {
        return isMethodIndifferent(method) || isMethodAlways(method);
      }

    });

    const reportUnexpectedProperty = (decl, logicalProperty) => stylelint.utils.report({
      message: messages.unexpectedProp(decl.prop, logicalProperty),
      node: decl,
      result,
      ruleName
    });

    const reportUnsupportedProp = (decl, logicalProperty) => stylelint.utils.report({
      message: messages.unsupportedProp(decl.prop, logicalProperty),
      node: decl,
      result,
      ruleName
    });

    const reportUnexpectedValue = (node, value) => stylelint.utils.report({
      message: messages.unexpectedValue(node.prop, node.value, value),
      node,
      result,
      ruleName
    });

    if (isMethodValid && isMethodAlways(method)) {
      walk(root, node => {
        migrationNoneSpec.forEach(([prop, props]) => {
          validateRuleWithProps(node, prop, outDateDecl => {
            console.warn(`Property ${prop[0]} is not part of Logical standards.`);

            if (isAutofixable(node)) {
              console.warn(`Migrating ${prop[0]} to Logical standards.`);
              const value = outDateDecl.value;
              outDateDecl.cloneBefore({
                prop: props[0],
                value
              });
              outDateDecl.cloneAfter({
                prop: props[1],
                value
              });
              outDateDecl.remove();
            } else if (!isDeclReported(outDateDecl)) {
              reportUnsupportedProp(outDateDecl, props);
              reportedDecls.set(outDateDecl);
            }
          });
        });
        /* logical shorthands do not work yet in browsers */

        /* but we can still use shorthand if all values are the same */
        // validate or autofix 4 physical properties as logical shorthands

        physical4Prop.forEach(([props, prop]) => {
          validateRuleWithProps(node, props, (blockStartDecl, blockStartIndex, inlineStartDecl, inlineStartIndex, blockEndDecl, blockEndIndex, inlineEndDecl, inlineEndIndex) => {
            // eslint-disable-line
            const firstInlineDecl = blockStartDecl;
            const values = shorthandValueShorten([blockStartDecl.value, inlineStartDecl.value, blockEndDecl.value, inlineEndDecl.value]);

            if (!isDeclAnException(blockStartDecl, propExceptions) && !isDeclAnException(inlineStartDecl, propExceptions) && !isDeclAnException(blockEndDecl, propExceptions) && !isDeclAnException(inlineEndDecl, propExceptions) && values.length === 1 // only report issues if there is 1 value after shortening
            ) {
                if (isAutofixable(node)) {
                  firstInlineDecl.cloneBefore({
                    prop,
                    value: values.join(' ')
                  });
                  blockStartDecl.remove();
                  inlineStartDecl.remove();
                  blockEndDecl.remove();
                  inlineEndDecl.remove();
                } else if (!isDeclReported(blockStartDecl) && !isDeclReported(inlineStartDecl) && !isDeclReported(blockEndDecl) && !isDeclReported(inlineEndDecl)) {
                  reportUnexpectedProperty(firstInlineDecl, prop);
                  reportedDecls.set(blockStartDecl);
                  reportedDecls.set(inlineStartDecl);
                  reportedDecls.set(blockEndDecl);
                  reportedDecls.set(inlineEndDecl);
                }
              }
          });
        }); // validate or autofix shorthand properties that are not supported

        physicalShorthandProp.forEach(prop => {
          validateRuleWithProps(node, [prop], physicalDecl => {
            // eslint-disable-line
            const inputValues = cssValueSplit(physicalDecl.value);

            if (!isDeclAnException(physicalDecl, propExceptions) && inputValues.length !== 1) {
              if (isAutofixable(node)) {
                let outputValues = convertShorthandValues(inputValues, dir);
                ['block', 'inline'].forEach(type => {
                  physicalDecl.cloneBefore({
                    prop: prop + "-" + type,
                    value: outputValues[type]
                  });
                });
                physicalDecl.remove();
              } else if (!isDeclReported(physicalDecl)) {
                reportUnexpectedProperty(physicalDecl, `${prop}-block and ${prop}-inline`);
                reportedDecls.set(physicalDecl);
              }
            }
          });
        }); // validate or autofix 2 physical properties as logical shorthands

        physical2Prop(dir).forEach(([props, prop]) => {
          validateRuleWithProps(node, props, (startDecl, startIndex, endDecl, endStartIndex) => {
            // eslint-disable-line
            const firstInlineDecl = startIndex < endStartIndex ? startDecl : endDecl;

            if (!isDeclAnException(startDecl, propExceptions) && !isDeclAnException(endDecl, propExceptions)) {
              if (isAutofixable(node)) {
                firstInlineDecl.cloneBefore({
                  prop,
                  value: startDecl.value === endDecl.value ? startDecl.value : [startDecl.value, endDecl.value].join(' ')
                });
                startDecl.remove();
                endDecl.remove();
              } else if (!isDeclReported(startDecl) && !isDeclReported(endDecl)) {
                reportUnexpectedProperty(firstInlineDecl, prop);
                reportedDecls.set(startDecl);
                reportedDecls.set(endDecl);
              }
            }
          });
        }); // validate or autofix physical properties as logical

        physicalProp(dir).forEach(([props, prop]) => {
          validateRuleWithProps(node, props, physicalDecl => {
            if (!isDeclAnException(physicalDecl, propExceptions)) {
              if (isAutofixable(node)) {
                physicalDecl.prop = prop;
              } else if (!isDeclReported(physicalDecl)) {
                reportUnexpectedProperty(physicalDecl, prop);
                reportedDecls.set(physicalDecl);
              }
            }
          });
        }); // validate or autofix physical values as logical

        physicalValue(dir).forEach(([regexp, props]) => {
          if (isNodeMatchingDecl(node, regexp) && !isDeclAnException(node, propExceptions)) {
            const valuekey = node.value.toLowerCase();

            if (valuekey in props) {
              const value = props[valuekey];

              if (isAutofixable(node)) {
                node.value = value;
              } else {
                reportUnexpectedValue(node, value);
                reportedDecls.set(node);
              }
            }
          }
        }); // validate or autofix physical values containing properties as logical

        if (isNodeMatchingDecl(node, propsThatContainPropsInValue)) {
          const originalValue = node.value.toLowerCase();
          let value = originalValue;
          physicalProp(dir).forEach(([props, prop]) => {
            if (!isDeclAnException(node, propExceptions)) {
              props.forEach(searchProp => {
                if (!isValueAnException(searchProp, propExceptions)) {
                  let regex = new RegExp("(?<!-)" + searchProp + "(?!-)", "g");

                  if (regex.test(value)) {
                    value = value.replace(regex, prop);
                  }
                }
              });
            }
          });

          if (value !== originalValue) {
            if (isAutofixable(node)) {
              node.value = value;
            } else {
              reportUnexpectedValue(node, value);
              reportedDecls.set(node);
            }
          }
        }
      });
    }
  };
}

ruleFunc.ruleName = ruleName;
var index = stylelint.createPlugin(ruleName, ruleFunc);

const isMethodIndifferent = method => method === 'ignore' || method === false || method === null;

const isMethodAlways = method => method === 'always' || method === true;

const isContextAutofixing = context => Boolean(Object(context).fix);

const isNodeMatchingDecl = (decl, regexp) => decl.type === 'decl' && regexp.test(decl.prop);

const isDeclAnException = (decl, propExceptions) => isValueAnException(decl.prop, propExceptions);

const isValueAnException = (value, propExceptions) => propExceptions.some(match => match instanceof RegExp ? match.test(value) : String(match || '').toLowerCase() === String(value || '').toLowerCase());

const isDeclReported = decl => reportedDecls.has(decl);

const shorthandValueShorten = values => {
  const map = [[1, 0], [0, 2], [1, 3]];

  for (let x = values.length - 2; x >= 0; x--) {
    if (values[map[x][0]] !== values[map[x][1]]) {
      break;
    }

    values.pop();
  }

  return values;
};

const convertShorthandValues = (input, dir) => {
  let block, inline;

  if (input.length === 1) {
    block = input[0];
    inline = input[0];
  }

  if (input.length === 2) {
    block = input[0];
    inline = input[1];
  }

  if (input.length === 3) {
    block = input[0] + ' ' + input[2];
    inline = input[1];
  }

  if (input.length === 4) {
    block = input[0] + ' ' + input[2];
    inline = dir === 'ltr' ? input[3] + ' ' + input[1] : input[1] + ' ' + input[3];
  }

  return {
    block: optimizeCssValues(block),
    inline: optimizeCssValues(inline)
  };
};

const optimizeCssValues = value => {
  const values = cssValueSplit(value);

  if (values.length === 2 && values[0] === values[1]) {
    return values[0];
  }

  return value;
};

export default index;
//# sourceMappingURL=index.es.mjs.map
