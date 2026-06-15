import { createMarkdownRenderer } from '@caiwu/shared/content'

export const renderMarkdown = createMarkdownRenderer({
  demoteHeadings: true,
  imageAltFallback: 'image',
})
