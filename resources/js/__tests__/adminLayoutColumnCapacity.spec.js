import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const editorSources = [
  {
    name: 'Simple Page Builder',
    path: 'resources/views/admin/page/builder-simple.blade.php',
    endMarker: 'function elementPickerContext()',
  },
  {
    name: 'Reusable Section editor',
    path: 'resources/views/admin/reusable-blocks/editor.blade.php',
    endMarker: 'const validLayoutId',
  },
]

const layoutElementCatalog = {
  accordion: { safe_bounds: { max_instances_per_column: 4 } },
  heading: { safe_bounds: { max_instances_per_column: 12 } },
  video: { safe_bounds: { max_instances_per_column: 6 } },
  gallery: { safe_bounds: { max_instances_per_column: 3 } },
  file: { safe_bounds: { max_instances_per_column: 8 } },
}

function capacityPlanner({ path, endMarker }) {
  const source = readFileSync(resolve(process.cwd(), path), 'utf8')
  const start = source.indexOf('const layoutColumnElementLimit =')
  const end = source.indexOf(endMarker, start)

  expect(start).toBeGreaterThanOrEqual(0)
  expect(end).toBeGreaterThan(start)

  const helpers = source.slice(start, end)
  return new Function(
    'layoutElementCatalog',
    'layoutStorageLimits',
    `${helpers}\nreturn { planLayoutColumnCollapse, layoutColumnIsWithinCapacity };`,
  )(layoutElementCatalog, { elementsPerColumn: 12 })
}

function feasibleThreeToTwoColumns() {
  let sequence = 0
  const elements = (type, count) => Array.from(
    { length: count },
    () => ({ id: `fixture-${sequence += 1}`, type }),
  )

  return [
    {
      id: 'kept-one',
      elements: [...elements('accordion', 2), ...elements('heading', 2)],
    },
    {
      id: 'kept-two',
      elements: [
        ...elements('heading', 3),
        ...elements('video', 2),
        ...elements('gallery', 2),
        ...elements('file', 2),
        ...elements('accordion', 1),
      ],
    },
    {
      id: 'removed-three',
      elements: [
        ...elements('accordion', 4),
        ...elements('gallery', 2),
        ...elements('video', 1),
        ...elements('file', 1),
        ...elements('heading', 2),
      ],
    },
  ]
}

describe.each(editorSources)('$name layout capacity planner', (editor) => {
  test('finds the feasible constrained 3-to-2 column distribution atomically', () => {
    const { planLayoutColumnCollapse, layoutColumnIsWithinCapacity } = capacityPlanner(editor)
    const columns = feasibleThreeToTwoColumns()
    const original = structuredClone(columns)
    const removedOrder = columns[2].elements.map((element) => element.id)

    const planned = planLayoutColumnCollapse(columns, 2)

    expect(planned).not.toBeNull()
    expect(columns).toEqual(original)
    expect(planned.map((column) => column.elements.length)).toEqual([12, 12])
    expect(planned.every(layoutColumnIsWithinCapacity)).toBe(true)
    expect(planned[0].elements.slice(0, 4)).toEqual(original[0].elements)
    expect(planned[1].elements.slice(0, 10)).toEqual(original[1].elements)

    for (const column of planned) {
      const appendedOrder = column.elements
        .map((element) => removedOrder.indexOf(element.id))
        .filter((index) => index >= 0)
      expect(appendedOrder).toEqual([...appendedOrder].sort((left, right) => left - right))
    }

    const plannedAgain = planLayoutColumnCollapse(feasibleThreeToTwoColumns(), 2)
    expect(plannedAgain).toEqual(planned)
  })

  test('leaves the source untouched when a type limit makes collapse impossible', () => {
    const { planLayoutColumnCollapse } = capacityPlanner(editor)
    const columns = [
      { id: 'kept', elements: Array.from({ length: 4 }, (_, index) => ({ id: `kept-${index}`, type: 'accordion' })) },
      { id: 'removed', elements: [{ id: 'removed-accordion', type: 'accordion' }] },
    ]
    const original = structuredClone(columns)

    expect(planLayoutColumnCollapse(columns, 1)).toBeNull()
    expect(columns).toEqual(original)
  })
})
