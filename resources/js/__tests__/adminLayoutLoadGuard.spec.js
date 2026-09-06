import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const editorSources = [
  {
    name: 'Simple Page Builder',
    path: 'resources/views/admin/page/builder-simple.blade.php',
  },
  {
    name: 'Reusable Section editor',
    path: 'resources/views/admin/reusable-blocks/editor.blade.php',
  },
]

const layoutStorageLimits = {
  payloadBytes: 524288,
  rows: 12,
  columns: 4,
  elementsPerColumn: 12,
}

const layoutElementCatalog = {
  heading: {
    label: 'Heading',
    safe_bounds: { max_instances_per_column: 12, max_serialized_bytes: 65536 },
  },
  video: {
    label: 'Video',
    safe_bounds: { max_instances_per_column: 6, max_serialized_bytes: 65536 },
  },
  gallery: {
    label: 'Photo gallery',
    safe_bounds: { max_instances_per_column: 3, max_serialized_bytes: 65536 },
  },
}

const layoutGlobalDesignChoices = {
  section_presentation: { standard: 'Standard', soft: 'Soft background', framed: 'Framed panel', contrast: 'Dark contrast' },
  section_spacing: { compact: 'Compact', standard: 'Standard', spacious: 'Spacious' },
  content_alignment: { left: 'Left', center: 'Centered' },
  column_count: { auto: 'Automatic', 2: 'Two columns', 3: 'Three columns', 4: 'Four columns' },
}

function loadBoundsGuard(path) {
  const source = readFileSync(resolve(process.cwd(), path), 'utf8')
  const start = source.indexOf('function layoutSerializedByteLength(value)')
  const end = source.indexOf('function inspectStoredLayout', start)

  expect(start).toBeGreaterThanOrEqual(0)
  expect(end).toBeGreaterThan(start)

  const helpers = source.slice(start, end)
  return new Function(
    'layoutStorageLimits',
    'layoutElementCatalog',
    'isLayoutObject',
    'isStaticLayoutElementType',
    'positiveLayoutLimit',
    `${helpers}\nreturn { layoutSerializedByteLength, layoutStoredBoundsIssue };`,
  )(
    layoutStorageLimits,
    layoutElementCatalog,
    (value) => value !== null && typeof value === 'object' && !Array.isArray(value),
    (type) => Object.prototype.hasOwnProperty.call(layoutElementCatalog, String(type || '')),
    (value, fallback) => {
      const parsed = Math.floor(Number(value))
      return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
    },
  )
}

function loadFullGuard(path) {
  const source = readFileSync(resolve(process.cwd(), path), 'utf8')
  const start = source.indexOf('function layoutStoredGlobalDesignIssue(value)')
  const end = source.indexOf('function layoutLoadGuard', start)

  expect(start).toBeGreaterThanOrEqual(0)
  expect(end).toBeGreaterThan(start)

  const functions = source.slice(start, end)
  return new Function(
    'layoutGlobalDesignChoices',
    'isLayoutObject',
    'layoutStoredBoundsIssue',
    `${functions}\nreturn inspectStoredLayout;`,
  )(
    layoutGlobalDesignChoices,
    (value) => value !== null && typeof value === 'object' && !Array.isArray(value),
    () => null,
  )
}

const element = (type, extra = {}) => ({ type, ...extra })
const column = (elements = []) => ({ elements })
const row = (columns = [column()]) => ({ columns })
const layout = (rows = [row()]) => ({ schema_version: 2, rows })

describe.each(editorSources)('$name stored-layout bounds guard', ({ path }) => {
  test('accepts ordinary content and measures UTF-8 bytes like the server', () => {
    const { layoutSerializedByteLength, layoutStoredBoundsIssue } = loadBoundsGuard(path)
    const value = layout([row([column([element('heading', { text: 'বাংলা 😀' })])])])
    const separators = { text: '\u2028\u2029' }
    const phpEquivalent = JSON.stringify(separators)
      .replace(/\u2028/g, '\\u2028')
      .replace(/\u2029/g, '\\u2029')

    expect(layoutStoredBoundsIssue(value)).toBeNull()
    expect(layoutSerializedByteLength(value)).toBe(Buffer.byteLength(JSON.stringify(value), 'utf8'))
    expect(layoutSerializedByteLength(separators)).toBe(Buffer.byteLength(phpEquivalent, 'utf8'))
  })

  test('locks layouts beyond the row and per-type limits', () => {
    const { layoutStoredBoundsIssue } = loadBoundsGuard(path)
    const tooManyRows = layout(Array.from({ length: 13 }, () => row()))
    const tooManyVideos = layout([row([column(Array.from({ length: 7 }, () => element('video')))])])
    const tooManyGalleries = layout([row([column(Array.from({ length: 4 }, () => element('gallery')))])])

    expect(layoutStoredBoundsIssue(tooManyRows)).toMatch(/12.*rows/i)
    expect(layoutStoredBoundsIssue(tooManyVideos)).toMatch(/(more than 6|too many) Video items/i)
    expect(layoutStoredBoundsIssue(tooManyGalleries)).toMatch(/(more than 3|too many) Photo gallery items/i)
  })

  test('locks an oversized element and an oversized complete payload', () => {
    const { layoutStoredBoundsIssue } = loadBoundsGuard(path)
    const oversizedElement = layout([row([column([
      element('heading', { text: 'x'.repeat(66000) }),
    ])])])
    const oversizedPayload = layout([row([column(
      Array.from({ length: 9 }, () => element('heading', { text: 'x'.repeat(60000) })),
    )])])

    expect(layoutStoredBoundsIssue(oversizedElement)).toContain('content item 1 is too large')
    expect(layoutStoredBoundsIssue(oversizedPayload)).toContain('visual layout is too large')
  })
})

describe.each(editorSources)('$name complete stored-layout guard', ({ path }) => {
  test('accepts supported global design choices and optional omissions', () => {
    const inspectStoredLayout = loadFullGuard(path)

    expect(inspectStoredLayout({ schema_version: 2, rows: [] }).blocked).toBe(false)
    expect(inspectStoredLayout({
      schema_version: 2,
      rows: [],
      section_presentation: 'soft',
      section_spacing: 'spacious',
      content_alignment: 'center',
      column_count: '3',
    }).blocked).toBe(false)
  })

  test.each(Object.keys(layoutGlobalDesignChoices))('locks unsupported %s values before editing', (key) => {
    const inspectStoredLayout = loadFullGuard(path)
    const result = inspectStoredLayout({ schema_version: 2, rows: [], [key]: 'future-choice' })

    expect(result.blocked).toBe(true)
    expect(result.detail).toMatch(/design choice.*does not support/i)
  })

  test('locks non-string global design values just as the server does', () => {
    const inspectStoredLayout = loadFullGuard(path)
    const result = inspectStoredLayout({ schema_version: 2, rows: [], column_count: 3 })

    expect(result.blocked).toBe(true)
    expect(result.detail).toMatch(/design choice.*does not support/i)
  })
})
