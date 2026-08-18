import { createMarkdownRenderer } from '@caiwu/shared/content/createMarkdownRenderer';

export const renderMarkdown = createMarkdownRenderer({
  demoteHeadings: true,
  imageAltFallback: 'image',
});
