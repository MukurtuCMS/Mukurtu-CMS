const postcss = require('postcss')
const fs = require('fs');
const path = require('path');

const plugin = require('./')

async function run (input, contains, opts = { }) {
  await postcss([plugin(opts).generate]).process(input, { from: undefined })
  const data = JSON.parse(fs.readFileSync(opts.targetPath, 'utf8') );
  contains.forEach((item)=>{
    expect(data.includes(item)).toEqual(true)
  })

}
it('Check simple classes.', async () => {
  const targetPath  = path.join(__dirname, 'export.json');
  await run('.parent{ } .glb-selector { } ', ['glb-selector'], { targetPath })
});

it('Check sub selectors.', async () => {
  const targetPath  = path.join(__dirname, 'export.json');
  await run('.parent .glb-selector { } ', ['glb-selector'], { targetPath })
});
it('Check not selectors.', async () => {
  const targetPath  = path.join(__dirname, 'export.json');
  await run('.glb-not-selector:not(.glb-not-inner) {}', ['glb-not-selector', 'glb-not-inner'], { targetPath })
});
