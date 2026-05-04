/**
 * Canonical filter node data: { relation: 'AND'|'OR', conditions: [...] }
 * Legacy { field, operator, value, valueType } is normalized on read.
 */

function emptyCondition() {
  return { field: '', operator: '=', value: '', valueType: 'CHAR' };
}

/**
 * @param {Object|null|undefined} data
 * @returns {{ relation: string, conditions: Array<Object> }}
 */
export function normalizeFilterNodeData(data) {
  if (!data || typeof data !== 'object') {
    return { relation: 'AND', conditions: [emptyCondition()] };
  }

  const hasConditionsArray = Array.isArray(data.conditions);
  const hasTopLevelFieldKey = Object.prototype.hasOwnProperty.call(data, 'field');
  const hasTopLevelOperatorKey = Object.prototype.hasOwnProperty.call(data, 'operator');
  const hasTopLevelValueKey = Object.prototype.hasOwnProperty.call(data, 'value');
  const hasTopLevelValueTypeKey = Object.prototype.hasOwnProperty.call(data, 'valueType');

  const legacyShape =
    !hasConditionsArray ||
    hasTopLevelFieldKey ||
    hasTopLevelOperatorKey ||
    hasTopLevelValueKey ||
    hasTopLevelValueTypeKey;

  if (legacyShape) {
    const cond = {
      field: data.field != null ? String(data.field) : '',
      operator: data.operator != null ? String(data.operator) : '=',
      value: data.value !== undefined ? data.value : '',
      valueType: data.valueType || 'CHAR',
    };
    if (data.isCustomField) {
      cond.isCustomField = true;
    }
    const relation = data.relation === 'OR' ? 'OR' : 'AND';
    return { relation, conditions: [cond] };
  }

  const relation = data.relation === 'OR' ? 'OR' : 'AND';
  const raw = data.conditions.length ? data.conditions : [emptyCondition()];
  const conditions = raw.map((c) => {
    const row = {
      field: c?.field != null ? String(c.field) : '',
      operator: c?.operator != null ? String(c.operator) : '=',
      value: c?.value !== undefined ? c.value : '',
      valueType: c?.valueType || 'CHAR',
    };
    if (c?.isCustomField) {
      row.isCustomField = true;
    }
    return row;
  });

  return { relation, conditions };
}

/**
 * @param {Object|null|undefined} data
 * @param {number} index
 * @returns {Object}
 */
export function getFilterCondition(data, index) {
  const n = normalizeFilterNodeData(data);
  return n.conditions[index] || {};
}

/**
 * True when every condition row has no field and no meaningful value (matches schema Fix 2 / validator).
 * @param {Object|null|undefined} data
 * @returns {boolean}
 */
export function isFilterNodeIncomplete(data) {
  const { conditions } = normalizeFilterNodeData(data);
  return conditions.every((c) => conditionRowIsBothEmpty(c));
}

function conditionRowIsBothEmpty(c) {
  if (String(c.field || '').trim()) {
    return false;
  }
  const op = String(c.operator || '').toUpperCase();
  if (op === 'EXISTS' || op === 'NOT EXISTS') {
    return true;
  }
  if (op === 'BETWEEN') {
    const v = c.value;
    if (!v || typeof v !== 'object') {
      return true;
    }
    return !String(v.from || '').trim() && !String(v.to || '').trim();
  }
  return c.value === undefined || c.value === '';
}

/**
 * Persistable filter payload without legacy top-level field/operator/value keys.
 *
 * @param {Object|null|undefined} data
 * @returns {Object}
 */
export function migrateFilterNodeDataToCanonical(data) {
  const normalized = normalizeFilterNodeData(data);
  const next = {
    label:
      data && typeof data === 'object' && data.label != null && String(data.label).trim() !== ''
        ? String(data.label)
        : 'Filter',
    relation: normalized.relation,
    conditions: normalized.conditions,
  };
  if (data && typeof data === 'object' && Object.prototype.hasOwnProperty.call(data, '_needsValidation')) {
    next._needsValidation = data._needsValidation;
  }
  return next;
}

/**
 * Saved graphs before multi-condition filters omit conditions[] or keep legacy flat keys.
 *
 * @param {Object|null|undefined} data
 * @returns {boolean}
 */
export function isLegacyFilterNodeShape(data) {
  if (!data || typeof data !== 'object') {
    return false;
  }
  if (!Array.isArray(data.conditions)) {
    return true;
  }
  return (
    Object.prototype.hasOwnProperty.call(data, 'field') ||
    Object.prototype.hasOwnProperty.call(data, 'operator') ||
    Object.prototype.hasOwnProperty.call(data, 'value') ||
    Object.prototype.hasOwnProperty.call(data, 'valueType')
  );
}
