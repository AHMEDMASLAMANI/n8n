const MAX_TITLE_LENGTH = 200;
const MIN_KEYWORDS = 5;
const MAX_KEYWORDS = 49;
const BANNED_TERMS = [
  'adobe',
  'getty',
  'shutterstock',
  'watermark',
  'logo',
  'brand',
  'trademark',
  'copyright',
  'iphone',
  'nike',
  'coca cola',
];

function normalizeKeyword(keyword) {
  return keyword
    .trim()
    .toLowerCase()
    .replace(/[\s_]+/g, ' ');
}

function findBannedTerms(text) {
  const normalizedText = text.toLowerCase();
  return BANNED_TERMS.filter((term) => normalizedText.includes(term));
}

function sanitizeKeywords(keywords) {
  const seen = new Set();
  const cleaned = [];

  for (const keyword of keywords) {
    const normalized = normalizeKeyword(String(keyword));
    if (!normalized || seen.has(normalized)) continue;
    seen.add(normalized);
    cleaned.push(normalized);
  }

  return cleaned;
}

function validateMetadata(metadata) {
  const warnings = [];
  const errors = [];

  const title = String(metadata.title ?? '').trim();
  const description = String(metadata.description ?? '').trim();
  const category = String(metadata.category ?? '').trim();
  const keywords = sanitizeKeywords(Array.isArray(metadata.keywords) ? metadata.keywords : []);

  if (!title) errors.push('Title is required.');
  if (title.length > MAX_TITLE_LENGTH) {
    errors.push(`Title must be <= ${MAX_TITLE_LENGTH} characters.`);
  }

  if (!description) warnings.push('Description is empty; Adobe Stock ranking can be weaker without context.');

  if (!category) warnings.push('Category is missing. Use Adobe Stock categories when available.');

  if (keywords.length < MIN_KEYWORDS || keywords.length > MAX_KEYWORDS) {
    errors.push(`Keywords count must be between ${MIN_KEYWORDS}-${MAX_KEYWORDS}.`);
  }

  const titleBanned = findBannedTerms(title);
  const descriptionBanned = findBannedTerms(description);
  const keywordBanned = keywords.flatMap((keyword) => findBannedTerms(keyword));

  const bannedFound = [...new Set([...titleBanned, ...descriptionBanned, ...keywordBanned])];
  if (bannedFound.length > 0) {
    errors.push(`Potential policy violation terms found: ${bannedFound.join(', ')}`);
  }

  const top10Keywords = keywords.slice(0, 10);
  if (top10Keywords.length < 5) {
    warnings.push('Top 10 keywords should contain the most important searchable concepts.');
  }

  return {
    isValid: errors.length === 0,
    errors,
    warnings,
    metadata: {
      title,
      description,
      category,
      keywords,
    },
  };
}

function buildPromptForVisionModel({ language = 'en', niche = 'stock photography' } = {}) {
  return `You are an Adobe Stock metadata assistant for ${niche}.
Output strict JSON with keys: title, description, category, keywords.
Rules:
- Title: concise, clear, factual, <= ${MAX_TITLE_LENGTH} chars.
- Description: one or two natural sentences, no brands, no trademark references.
- Keywords: ${MIN_KEYWORDS}-${MAX_KEYWORDS} unique keywords, ordered by importance.
- Avoid copyrighted names, logos, private personal identifiers.
- Match Adobe Stock quality and relevance expectations.
- Language: ${language}.
Only output JSON.`;
}

module.exports = {
  validateMetadata,
  sanitizeKeywords,
  buildPromptForVisionModel,
  constants: {
    MAX_TITLE_LENGTH,
    MIN_KEYWORDS,
    MAX_KEYWORDS,
    BANNED_TERMS,
  },
};
