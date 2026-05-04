// React is provided by WordPress via wp-element (available globally)
const { useState, useEffect, useRef, useCallback } = React;
import { __, sprintf } from '@wordpress/i18n';
import UpsellModal from './UpsellModal';
import {
  normalizeFilterNodeData,
  getFilterCondition,
  isLegacyFilterNodeShape,
} from '../utils/normalizeFilterNode.js';

const STANDARD_FIELD_KEYS = new Set(['post_title', 'post_date', 'post_author', 'post_content', 'post_excerpt']);

function fieldTypeForCondition(cond, fieldsList) {
  const field = cond?.field || '';
  if (!field) {
    return 'standard';
  }
  if (typeof field === 'string' && field.startsWith('tax:')) {
    return 'taxonomy';
  }
  if (cond.isCustomField) {
    return 'meta';
  }
  const found = fieldsList.find((f) => f.key === field);
  if (found?.type === 'taxonomy') {
    return 'taxonomy';
  }
  if (found?.type === 'standard') {
    return 'standard';
  }
  if (STANDARD_FIELD_KEYS.has(field)) {
    return 'standard';
  }
  return 'meta';
}

function getSelectedFieldTypeFromNode(node) {
  if (node?.type !== 'filter') {
    return 'standard';
  }
  const cond = getFilterCondition(node?.data, 0);
  return fieldTypeForCondition(cond, []);
}

function getTaxonomyValueSegment(value) {
  if (value == null || typeof value !== 'string') {
    return '';
  }
  const lastComma = value.lastIndexOf(',');
  const seg = lastComma >= 0 ? value.slice(lastComma + 1) : value;
  return seg.trim();
}

const NodeSettingsPanel = ({ node, onUpdate, onClose, sourceNodes = [], currentLogicJson = '' }) => {
  const [upsellModal, setUpsellModal] = useState({ isOpen: false, featureName: '', description: '' });
  const [settings, setSettings] = useState(node?.data || {});
  const [fields, setFields] = useState([]); // All fields: standard + taxonomy + meta
  const [selectedFieldType, setSelectedFieldType] = useState(() => getSelectedFieldTypeFromNode(node));
  const [loadingMetaKeys, setLoadingMetaKeys] = useState(false);
  const [showCustomField, setShowCustomField] = useState(false);
  const [showDynamicTags, setShowDynamicTags] = useState(false);
  const [activeField, setActiveField] = useState(null); // Track which field is active for dynamic tags
  const dynamicTagsRef = useRef(null);
  const taxTermSuggestRef = useRef(null);
  const taxTermRowIndexRef = useRef(0);
  const taxTermDebounceRef = useRef(null);
  const taxTermAbortRef = useRef(null);
  const [taxTermLoading, setTaxTermLoading] = useState(false);
  const [taxTermSuggestions, setTaxTermSuggestions] = useState([]);
  const [taxTermEmpty, setTaxTermEmpty] = useState(false);
  const [taxFocusedRow, setTaxFocusedRow] = useState(-1);

  const clearTaxTermSearch = useCallback(() => {
    setTaxTermSuggestions([]);
    setTaxTermEmpty(false);
    setTaxTermLoading(false);
    setTaxFocusedRow(-1);
    if (taxTermDebounceRef.current) {
      clearTimeout(taxTermDebounceRef.current);
      taxTermDebounceRef.current = null;
    }
    taxTermAbortRef.current?.abort();
  }, []);

  useEffect(() => {
    if (node?.type === 'filter') {
      const canon = normalizeFilterNodeData(node.data || {});
      setSettings({
        label: node.data?.label || 'Filter',
        relation: canon.relation,
        conditions: canon.conditions.map((c) => ({
          field: c.field || '',
          operator: c.operator || '=',
          value: c.value !== undefined ? c.value : '',
          valueType: c.valueType || 'CHAR',
          isCustomField: !!c.isCustomField,
        })),
        _needsValidation: node.data?._needsValidation,
        _migrationReviewPending: node.data?._migrationReviewPending,
      });
      const c0 = getFilterCondition(node.data, 0);
      setShowCustomField(!!c0.isCustomField);
      setSelectedFieldType(fieldTypeForCondition(c0, fields));
    } else {
      setSettings(node?.data || {});
      setSelectedFieldType(getSelectedFieldTypeFromNode(node));
      setShowCustomField(false);
    }
    clearTaxTermSearch();
    setShowDynamicTags(false);
    setActiveField(null);
  }, [node, clearTaxTermSearch]);

  // Fetch meta keys when filter node is selected and we have post types.
  useEffect(() => {
    if (node?.type === 'filter' && sourceNodes.length > 0) {
      // Get post types from source nodes - use first one for now.
      // In future, could merge meta keys from all source post types.
      const firstSource = sourceNodes[0];
      const postType = firstSource?.data?.postType || 'post';
      fetchMetaKeys(postType);
    }
  }, [node?.type, sourceNodes]);

  // Close dynamic tags dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dynamicTagsRef.current && !dynamicTagsRef.current.contains(event.target)) {
        setShowDynamicTags(false);
      }
    };

    if (showDynamicTags) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [showDynamicTags]);

  useEffect(() => {
    const open =
      taxFocusedRow >= 0 &&
      (taxTermLoading || taxTermSuggestions.length > 0 || taxTermEmpty);
    if (!open) {
      return undefined;
    }
    const handle = (e) => {
      if (taxTermSuggestRef.current && !taxTermSuggestRef.current.contains(e.target)) {
        setTaxTermSuggestions([]);
        setTaxTermEmpty(false);
      }
    };
    document.addEventListener('mousedown', handle);
    return () => document.removeEventListener('mousedown', handle);
  }, [taxFocusedRow, taxTermLoading, taxTermSuggestions.length, taxTermEmpty]);

  useEffect(() => {
    return () => {
      if (taxTermDebounceRef.current) {
        clearTimeout(taxTermDebounceRef.current);
      }
      taxTermAbortRef.current?.abort();
    };
  }, []);

  // Insert dynamic tag into value field
  const insertDynamicTag = (tag) => {
    const currentValue = settings.value || '';
    const newValue = currentValue + (currentValue ? ' ' : '') + tag;
    const updatedSettings = { ...settings, value: newValue };
    setSettings(updatedSettings);
    onUpdate(node.id, updatedSettings);
    setShowDynamicTags(false);
  };

  if (!node) {
    return null;
  }

  const handleSave = (e) => {
    if (e) {
      e.stopPropagation();
      e.preventDefault();
    }
    onUpdate(node.id, settings);
    // Don't close the panel - keep it open so user can continue editing
    // onClose();
  };

  const fetchMetaKeys = async (postType) => {
    setLoadingMetaKeys(true);
    const ajaxUrl = window.QueryForgeConfig?.ajaxUrl;
    const nonce = window.QueryForgeConfig?.nonce || '';
    if (!ajaxUrl) {
      setLoadingMetaKeys(false);
      return;
    }
    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'query_forge_get_meta_keys',
          post_type: postType,
          nonce,
        }),
      });
      const data = await response.json();
      if (data.success) {
        // Use new 'fields' array if available, fallback to 'meta_keys' for backward compatibility.
        if (data.data.fields && Array.isArray(data.data.fields)) {
          setFields(data.data.fields);
        } else if (data.data.meta_keys) {
          // Legacy format - convert to new format.
          const legacyFields = data.data.meta_keys.map(key => ({
            key: key,
            label: key,
            type: 'meta'
          }));
          setFields(legacyFields);
        }
      }
    } catch (error) {
      // Silently handle fetch errors
    } finally {
      setLoadingMetaKeys(false);
    }
  };

  const scheduleTaxTermSearch = (segment, fieldKey) => {
    if (taxTermDebounceRef.current) {
      clearTimeout(taxTermDebounceRef.current);
      taxTermDebounceRef.current = null;
    }
    taxTermAbortRef.current?.abort();

    if (!fieldKey || typeof fieldKey !== 'string' || !fieldKey.startsWith('tax:')) {
      setTaxTermSuggestions([]);
      setTaxTermEmpty(false);
      setTaxTermLoading(false);
      return;
    }
    const taxonomy = fieldKey.slice(4);
    if (taxonomy.length < 1 || segment.length < 1) {
      setTaxTermSuggestions([]);
      setTaxTermEmpty(false);
      setTaxTermLoading(false);
      return;
    }

    taxTermDebounceRef.current = setTimeout(async () => {
      const ac = new AbortController();
      taxTermAbortRef.current = ac;
      setTaxTermLoading(true);
      setTaxTermEmpty(false);
      try {
        const ajaxUrl = window.QueryForgeConfig?.ajaxUrl;
        const nonce = window.QueryForgeConfig?.nonce || '';
        if (!ajaxUrl) {
          setTaxTermLoading(false);
          return;
        }
        const response = await fetch(ajaxUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'query_forge_search_terms',
            nonce,
            taxonomy,
            search: segment,
          }),
          signal: ac.signal,
        });
        const data = await response.json();
        if (data.success && data.data && Array.isArray(data.data.terms)) {
          setTaxTermSuggestions(data.data.terms);
          setTaxTermEmpty(data.data.terms.length === 0);
        } else {
          setTaxTermSuggestions([]);
          setTaxTermEmpty(true);
        }
      } catch (err) {
        if (err.name === 'AbortError') {
          return;
        }
        setTaxTermSuggestions([]);
        setTaxTermEmpty(false);
      } finally {
        setTaxTermLoading(false);
      }
    }, 300);
  };

  const selectTaxonomyTermSlug = (slug) => {
    if (node?.type !== 'filter') {
      const nextSettings = { ...settings, value: slug };
      setSettings(nextSettings);
      onUpdate(node.id, nextSettings);
      setTaxTermSuggestions([]);
      setTaxTermEmpty(false);
      setTaxTermLoading(false);
      setTaxFocusedRow(-1);
      return;
    }
    const idx = taxTermRowIndexRef.current;
    const conds = [...(settings.conditions || [])];
    if (!conds[idx]) {
      return;
    }
    conds[idx] = { ...conds[idx], value: slug };
    const payload = {
      label: settings.label || 'Filter',
      relation: settings.relation === 'OR' ? 'OR' : 'AND',
      conditions: conds,
    };
    if (settings._needsValidation !== undefined) {
      payload._needsValidation = settings._needsValidation;
    }
    setSettings((prev) => ({ ...prev, ...payload }));
    onUpdate(node.id, payload);
    setTaxTermSuggestions([]);
    setTaxTermEmpty(false);
    setTaxTermLoading(false);
    setTaxFocusedRow(-1);
  };

  const renderSourceSettings = () => {
    const qfConfig = window.QueryForgeConfig || {};
    const postTypes = qfConfig.postTypes || [];
    
    // Determine source type from current postType value or legacy postTypeCategory
    // If postType is 'post', sourceType is 'posts'
    // If postType is 'page', sourceType is 'pages'
    // Otherwise, assume it's a CPT (sourceType is 'cpts')
    const getSourceType = () => {
      // Check for legacy postTypeCategory first (backward compatibility)
      if (settings.postTypeCategory) {
        return settings.postTypeCategory;
      }
      
      // Otherwise infer from postType
      const currentPostType = settings.postType || 'post';
      if (currentPostType === 'post') {
        return 'posts';
      } else if (currentPostType === 'page') {
        return 'pages';
      } else {
        return 'cpts';
      }
    };
    
    const sourceType = settings.sourceType || getSourceType();
    
    // Built-in WordPress post types to exclude from CPTs list
    const builtInPostTypes = [
      'post', 'page', 'attachment', 'revision', 'nav_menu_item', 
      'custom_css', 'customize_changeset', 'oembed', 'user_request',
      'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
      'wp_navigation', 'wp_font_family', 'wp_font_face'
    ];
    
    // Filter post types based on source type selection
    const getFilteredPostTypes = () => {
      if (sourceType === 'posts') {
        return [{ name: 'post', label: 'Posts' }];
      } else if (sourceType === 'pages') {
        return [{ name: 'page', label: 'Pages' }];
      } else if (sourceType === 'cpts') {
        // Return only custom post types (exclude built-in types)
        return postTypes.filter(pt => !builtInPostTypes.includes(pt.name));
      }
      return [];
    };
    
    const filteredPostTypes = getFilteredPostTypes();
    
    return (
    <div>
      {/* First dropdown: Source Type */}
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Source Type:
        <select
          value={sourceType}
          onChange={(e) => {
            const newSourceType = e.target.value;
            let defaultPostType = 'post';
            
            // Set default post type based on source type
            if (newSourceType === 'posts') {
              defaultPostType = 'post';
            } else if (newSourceType === 'pages') {
              defaultPostType = 'page';
            } else if (newSourceType === 'cpts') {
              // Use first CPT as default if available
              const cpts = postTypes.filter(pt => !builtInPostTypes.includes(pt.name));
              if (cpts.length > 0) {
                defaultPostType = cpts[0].name;
              }
            }
            
            const updatedSettings = { 
              ...settings, 
              sourceType: newSourceType,
              postType: defaultPostType
            };
            setSettings(updatedSettings);
            onUpdate(node.id, updatedSettings);
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
        >
          <option value="posts">Posts</option>
          <option value="pages">Pages</option>
          <option value="cpts">Custom Post Types</option>
        </select>
      </label>
      
      {/* Second dropdown: Post Type (filtered by Source Type) */}
      <label style={{ display: 'block', marginBottom: '10px', marginTop: '10px' }}>
        Post Type:
        <select
          value={settings.postType || (sourceType === 'posts' ? 'post' : sourceType === 'pages' ? 'page' : '')}
          onChange={(e) => {
            const newPostType = e.target.value;
            const updatedSettings = { ...settings, postType: newPostType };
            setSettings(updatedSettings);
            onUpdate(node.id, updatedSettings);
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          disabled={filteredPostTypes.length === 0}
        >
          {filteredPostTypes.length > 0 ? (
            filteredPostTypes.map(pt => (
              <option key={pt.name} value={pt.name}>{pt.label || pt.name}</option>
            ))
          ) : (
            <option value="">No options available</option>
          )}
        </select>
        {sourceType === 'cpts' && filteredPostTypes.length === 0 && (
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', fontStyle: 'italic' }}>
            No custom post types found. Create a custom post type first.
          </div>
        )}
      </label>
    </div>
  );
  };

  const renderFilterSettings = () => {
    const standardFields = fields.filter((f) => f.type === 'standard');
    const taxonomyFields = fields.filter((f) => f.type === 'taxonomy');
    const metaFields = fields.filter((f) => f.type === 'meta');

    const conditions = Array.isArray(settings.conditions) ? settings.conditions : [];
    const isLegacyFilter = isLegacyFilterNodeShape(node?.data);

    const persistFilter = (partial) => {
      const nextConds = partial.conditions ?? conditions;
      const payload = {
        label: partial.label !== undefined ? partial.label : settings.label || __('Filter', 'query-forge'),
        relation: (partial.relation !== undefined ? partial.relation : settings.relation) === 'OR' ? 'OR' : 'AND',
        conditions: nextConds.map((c) => {
          const row = {
            field: c.field || '',
            operator: c.operator || '=',
            value: c.value !== undefined ? c.value : '',
            valueType: c.valueType || 'CHAR',
          };
          if (c.isCustomField) {
            row.isCustomField = true;
          }
          return row;
        }),
      };
      if (settings._needsValidation !== undefined) {
        payload._needsValidation = settings._needsValidation;
      }
      if (Object.prototype.hasOwnProperty.call(partial, '_migrationReviewPending')) {
        payload._migrationReviewPending = partial._migrationReviewPending;
      } else if (settings._migrationReviewPending !== undefined) {
        payload._migrationReviewPending = settings._migrationReviewPending;
      }
      setSettings((prev) => ({ ...prev, ...payload }));
      onUpdate(node.id, payload);
      const c0 = payload.conditions[0];
      if (c0) {
        setSelectedFieldType(fieldTypeForCondition(c0, fields));
      }
    };

    const removeCondition = (idx) => {
      if (idx === 0) {
        return;
      }
      persistFilter({ conditions: conditions.filter((_, i) => i !== idx) });
    };

    const addCondition = () => {
      const last = conditions[conditions.length - 1];
      if (!last || !String(last.field || '').trim()) {
        return;
      }
      persistFilter({
        conditions: [
          ...conditions,
          { field: '', operator: '=', value: '', valueType: 'CHAR' },
        ],
      });
    };

    const isDateRow = (cond) =>
      cond.field === 'post_date' ||
      cond.field === 'post_modified' ||
      cond.valueType === 'DATE';

    const renderValueInputs = (cond, idx, rowType) => {
      const op = String(cond.operator || '').toUpperCase();
      if (op === 'EXISTS' || op === 'NOT EXISTS') {
        return null;
      }
      if (op === 'BETWEEN') {
        const v =
          cond.value && typeof cond.value === 'object' && !Array.isArray(cond.value)
            ? cond.value
            : { from: '', to: '' };
        const dt = isDateRow(cond);
        return (
          <div style={{ display: 'flex', gap: '8px', marginTop: '5px' }}>
            <label style={{ flex: 1, fontSize: '11px' }}>
              {__('From', 'query-forge')}
              <input
                type={dt ? 'date' : 'text'}
                value={v.from || ''}
                onChange={(e) => {
                  const next = { ...v, from: e.target.value };
                  updateRow(idx, { value: next });
                }}
                style={{ width: '100%', padding: '5px', marginTop: '4px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              />
            </label>
            <label style={{ flex: 1, fontSize: '11px' }}>
              {__('To', 'query-forge')}
              <input
                type={dt ? 'date' : 'text'}
                value={v.to || ''}
                onChange={(e) => {
                  const next = { ...v, to: e.target.value };
                  updateRow(idx, { value: next });
                }}
                style={{ width: '100%', padding: '5px', marginTop: '4px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              />
            </label>
          </div>
        );
      }
      if (rowType === 'taxonomy') {
        return (
          <div
            ref={taxFocusedRow === idx ? taxTermSuggestRef : undefined}
            style={{ position: 'relative', marginTop: '5px' }}
          >
            <input
              type="text"
              value={typeof cond.value === 'string' ? cond.value : ''}
              onFocus={() => {
                taxTermRowIndexRef.current = idx;
                setTaxFocusedRow(idx);
              }}
              onChange={(e) => {
                const nextVal = e.target.value;
                taxTermRowIndexRef.current = idx;
                setTaxFocusedRow(idx);
                updateRow(idx, { value: nextVal });
                scheduleTaxTermSearch(nextVal.trim(), cond.field);
              }}
              placeholder={__('Single slug or term ID', 'query-forge')}
              style={{ width: '100%', padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              autoComplete="off"
            />
            {cond.field?.startsWith('tax:') &&
              taxFocusedRow === idx &&
              String(cond.value || '').trim().length >= 1 &&
              (taxTermLoading || taxTermSuggestions.length > 0 || taxTermEmpty) && (
                <div
                  style={{
                    position: 'absolute',
                    left: 0,
                    right: 0,
                    top: '100%',
                    marginTop: '4px',
                    background: '#1e1e1e',
                    border: '1px solid #444',
                    borderRadius: '4px',
                    maxHeight: '220px',
                    overflowY: 'auto',
                    zIndex: 1001,
                    boxShadow: '0 4px 12px rgba(0,0,0,0.35)',
                  }}
                >
                  {taxTermLoading && (
                    <div style={{ padding: '10px', fontSize: '12px', color: '#999' }}>
                      {__('Searching…', 'query-forge')}
                    </div>
                  )}
                  {!taxTermLoading &&
                    taxTermSuggestions.map((t) => (
                      <button
                        key={t.id}
                        type="button"
                        onMouseDown={(e) => {
                          e.preventDefault();
                          taxTermRowIndexRef.current = idx;
                          selectTaxonomyTermSlug(t.slug);
                        }}
                        style={{
                          display: 'block',
                          width: '100%',
                          textAlign: 'left',
                          padding: '8px 10px',
                          background: 'transparent',
                          color: '#e2e8f0',
                          border: 'none',
                          borderBottom: '1px solid #333',
                          cursor: 'pointer',
                          fontSize: '12px',
                        }}
                      >
                        <span style={{ fontWeight: 600 }}>{t.name}</span>
                        <span style={{ color: '#718096', marginLeft: '8px' }}>({t.slug})</span>
                      </button>
                    ))}
                  {!taxTermLoading && taxTermEmpty && (
                    <div style={{ padding: '10px', fontSize: '12px', color: '#718096' }}>
                      {__('No matching terms.', 'query-forge')}
                    </div>
                  )}
                </div>
              )}
          </div>
        );
      }
      const inpType = isDateRow(cond) ? 'date' : 'text';
      return (
        <input
          type={inpType}
          value={typeof cond.value === 'string' || typeof cond.value === 'number' ? String(cond.value) : ''}
          onChange={(e) => updateRow(idx, { value: e.target.value })}
          placeholder={__('Enter static text…', 'query-forge')}
          style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
        />
      );
    };

    const updateRow = (idx, patch) => {
      const next = conditions.map((c, i) => (i === idx ? { ...c, ...patch } : c));
      persistFilter({ conditions: next });
    };

    const handleRowFieldSelect = (idx, value) => {
      clearTaxTermSearch();
      const row = conditions[idx] || {};
      if (value === '__custom__') {
        const next = conditions.map((c, i) =>
          i === idx ? { ...c, field: '', operator: '=', value: '', valueType: 'CHAR', isCustomField: true } : c
        );
        persistFilter({ conditions: next });
        if (idx === 0) {
          setShowCustomField(true);
        }
        return;
      }
      const found = fields.find((f) => f.key === value);
      let nextType = 'meta';
      if (found) {
        if (found.type === 'standard') {
          nextType = 'standard';
        } else if (found.type === 'taxonomy') {
          nextType = 'taxonomy';
        }
      } else if (typeof value === 'string' && value.startsWith('tax:')) {
        nextType = 'taxonomy';
      } else if (STANDARD_FIELD_KEYS.has(value)) {
        nextType = 'standard';
      }
      let nextOp = row.operator || '=';
      if (nextType === 'taxonomy') {
        nextOp = 'IN';
      } else if (['IN', 'NOT IN', 'AND'].includes(String(nextOp))) {
        nextOp = '=';
      }
      const next = conditions.map((c, i) =>
        i === idx
          ? {
              ...c,
              field: value,
              isCustomField: false,
              operator: nextOp,
              value: '',
            }
          : c
      );
      persistFilter({ conditions: next });
      if (idx === 0) {
        setShowCustomField(false);
      }
    };

    const rel = settings.relation === 'OR' ? 'OR' : 'AND';

    return (
      <div>
        {settings._migrationReviewPending === true && (
          <div
            style={{
              marginBottom: '16px',
              padding: '12px',
              borderRadius: '6px',
              border: '1px solid #d69e2e',
              background: 'rgba(214, 158, 46, 0.15)',
              color: '#fff',
            }}
          >
            <p style={{ margin: '0 0 10px', fontSize: '13px', lineHeight: 1.45 }}>
              {__(
                'Please review this migrated filter, then confirm below. The amber outline clears after you confirm.',
                'query-forge'
              )}
            </p>
            <button
              type="button"
              onClick={() => {
                onUpdate(node.id, { _migrationReviewPending: false });
                setSettings((prev) => ({ ...prev, _migrationReviewPending: false }));
              }}
              style={{
                background: '#2d3748',
                color: '#fff',
                border: '1px solid #d69e2e',
                borderRadius: '6px',
                padding: '8px 14px',
                cursor: 'pointer',
                fontWeight: 600,
              }}
            >
              {__('Confirm migration review', 'query-forge')}
            </button>
          </div>
        )}
        {conditions.map((cond, idx) => {
          const rowType = fieldTypeForCondition(cond, fields);
          const showCustom = (idx === 0 && showCustomField) || !!cond.isCustomField;
          const opVal = String(cond.operator || '=');
          return (
            <div
              key={idx}
              style={{
                borderBottom: idx < conditions.length - 1 ? '1px solid #444' : 'none',
                paddingBottom: '14px',
                marginBottom: '14px',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                <strong style={{ fontSize: '13px' }}>
                  {sprintf(__('Condition %d', 'query-forge'), idx + 1)}
                </strong>
                {idx > 0 && (
                  <button
                    type="button"
                    onClick={() => removeCondition(idx)}
                    style={{
                      background: 'rgba(255,0,0,0.75)',
                      color: '#fff',
                      border: 'none',
                      borderRadius: '50%',
                      width: '22px',
                      height: '22px',
                      cursor: 'pointer',
                      fontSize: '14px',
                      lineHeight: 1,
                      padding: 0,
                    }}
                    title={__('Remove condition', 'query-forge')}
                  >
                    ×
                  </button>
                )}
              </div>
              <label style={{ display: 'block', marginBottom: '10px' }}>
                {__('Field:', 'query-forge')}
                {!showCustom ? (
                  <select
                    value={cond.field || ''}
                    onChange={(e) => handleRowFieldSelect(idx, e.target.value)}
                    style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
                    disabled={loadingMetaKeys}
                  >
                    <option value="">{__('-- Select Field --', 'query-forge')}</option>
                    {standardFields.length > 0 && (
                      <optgroup label={__('Standard Fields', 'query-forge')}>
                        {standardFields.map((field) => (
                          <option key={field.key} value={field.key}>
                            {field.label}
                          </option>
                        ))}
                      </optgroup>
                    )}
                    {taxonomyFields.length > 0 && (
                      <optgroup label={__('Taxonomies', 'query-forge')}>
                        {taxonomyFields.map((field) => (
                          <option key={field.key} value={field.key}>
                            {field.label}
                          </option>
                        ))}
                      </optgroup>
                    )}
                    {metaFields.length > 0 && (
                      <optgroup label={__('Custom Meta Fields', 'query-forge')}>
                        {metaFields.map((field) => (
                          <option key={field.key} value={field.key}>
                            {field.label}
                          </option>
                        ))}
                      </optgroup>
                    )}
                    <option value="__custom__">{__('-- Custom Field --', 'query-forge')}</option>
                  </select>
                ) : (
                  <div>
                    <input
                      type="text"
                      value={cond.field || ''}
                      onChange={(e) => updateRow(idx, { field: e.target.value, isCustomField: true })}
                      placeholder="e.g. _price, stock_level"
                      style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
                    />
                    <button
                      type="button"
                      onClick={() => {
                        if (idx === 0) setShowCustomField(false);
                        updateRow(idx, { field: '', isCustomField: false });
                      }}
                        style={{
                          marginTop: '5px',
                          padding: '4px 8px',
                          background: 'transparent',
                          color: '#999',
                          border: '1px solid #555',
                          borderRadius: '4px',
                          cursor: 'pointer',
                          fontSize: '11px',
                        }}
                      >
                        {__('Use dropdown instead', 'query-forge')}
                      </button>
                  </div>
                )}
              </label>
              {loadingMetaKeys && (
                <div style={{ fontSize: '11px', color: '#999', marginTop: '5px' }}>{__('Loading fields…', 'query-forge')}</div>
              )}
              <label style={{ display: 'block', marginBottom: '10px' }}>
                {__('Operator:', 'query-forge')}
                <select
                  value={
                    rowType === 'taxonomy'
                      ? ['IN', 'NOT IN', 'AND'].includes(opVal)
                        ? opVal
                        : 'IN'
                      : opVal
                  }
                  onChange={(e) => {
                    const newOperator = e.target.value;
                    if (rowType === 'taxonomy') {
                      const allowedTax = ['IN', 'NOT IN', 'AND'];
                      if (!allowedTax.includes(newOperator)) {
                        return;
                      }
                    }
                    let nextVal = cond.value;
                    if (newOperator === 'BETWEEN') {
                      nextVal = { from: '', to: '' };
                    } else if (String(cond.operator || '').toUpperCase() === 'BETWEEN') {
                      nextVal = '';
                    }
                    updateRow(idx, { operator: newOperator, value: nextVal });
                  }}
                  style={{ width: '100%', padding: '5px', marginTop: '5px' }}
                >
                  {rowType === 'taxonomy' ? (
                    <>
                      <option value="IN">{__('Has any of', 'query-forge')}</option>
                      <option value="NOT IN">{__('Does not have', 'query-forge')}</option>
                      <option value="AND">{__('Has all of', 'query-forge')}</option>
                    </>
                  ) : (
                    <>
                      <option value="=">=</option>
                      <option value="!=">!=</option>
                      <option value="LIKE">LIKE</option>
                      <option value="NOT LIKE">NOT LIKE</option>
                      <option value="BETWEEN">BETWEEN</option>
                      <option value="EXISTS">EXISTS</option>
                      <option value="NOT EXISTS">NOT EXISTS</option>
                      <option value="IN">IN</option>
                      <option value="NOT IN">NOT IN</option>
                    </>
                  )}
                </select>
              </label>
              {!['EXISTS', 'NOT EXISTS'].includes(String(cond.operator || '').toUpperCase()) && (
                <label style={{ display: 'block', marginBottom: '10px', position: 'relative' }}>
                  {__('Value:', 'query-forge')}
                  {renderValueInputs(cond, idx, rowType)}
                </label>
              )}
              {rowType !== 'taxonomy' && (
                <label style={{ display: 'block', marginBottom: '10px' }}>
                  {__('Value Type:', 'query-forge')}
                  <select
                    value={cond.valueType || 'CHAR'}
                    onChange={(e) => updateRow(idx, { valueType: e.target.value })}
                    style={{ width: '100%', padding: '5px', marginTop: '5px' }}
                  >
                    <option value="CHAR">{__('Text', 'query-forge')}</option>
                    <option value="NUMERIC">{__('Number', 'query-forge')}</option>
                    <option value="DATE">{__('Date', 'query-forge')}</option>
                  </select>
                </label>
              )}
              {idx < conditions.length - 1 && (
                <div style={{ marginTop: '12px', paddingTop: '10px', borderTop: '1px dashed #555' }}>
                  <span style={{ fontSize: '11px', color: '#a0aec0', marginRight: '8px' }}>
                    {__('Between rows:', 'query-forge')}
                  </span>
                  {idx === 0 ? (
                    <span style={{ display: 'inline-flex', gap: '6px' }}>
                      <button
                        type="button"
                        onClick={() => persistFilter({ relation: 'AND' })}
                        style={{
                          padding: '4px 10px',
                          borderRadius: '4px',
                          border: rel === 'AND' ? '2px solid #63b3ed' : '1px solid #555',
                          background: rel === 'AND' ? '#2c5282' : '#2d3748',
                          color: '#fff',
                          cursor: 'pointer',
                          fontSize: '11px',
                        }}
                      >
                        AND
                      </button>
                      <button
                        type="button"
                        onClick={() => persistFilter({ relation: 'OR' })}
                        style={{
                          padding: '4px 10px',
                          borderRadius: '4px',
                          border: rel === 'OR' ? '2px solid #63b3ed' : '1px solid #555',
                          background: rel === 'OR' ? '#2c5282' : '#2d3748',
                          color: '#fff',
                          cursor: 'pointer',
                          fontSize: '11px',
                        }}
                      >
                        OR
                      </button>
                    </span>
                  ) : (
                    <span style={{ fontSize: '11px', color: '#cbd5e0', fontWeight: 600 }}>{rel}</span>
                  )}
                </div>
              )}
            </div>
          );
        })}
        {!isLegacyFilter && (
          <button
            type="button"
            disabled={!conditions.length || !String(conditions[conditions.length - 1]?.field || '').trim()}
            onClick={addCondition}
            style={{
              marginTop: '8px',
              padding: '8px 12px',
              background: '#4a5568',
              color: '#fff',
              border: '1px solid #718096',
              borderRadius: '4px',
              cursor: 'pointer',
              fontSize: '12px',
            }}
          >
            {__('+ Add Condition', 'query-forge')}
          </button>
        )}
      </div>
    );
  };

  const renderSortSettings = () => (
    <div>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Sort By:
        <select
          value={settings.field || 'date'}
          onChange={(e) => {
            const newField = e.target.value;
            const restrictedSorts = ['rand', 'menu_order', 'meta_value', 'meta_value_num'];
            if (restrictedSorts.includes(newField)) {
              return;
            }
            const updatedSettings = { ...settings, field: newField };
            // Clear meta_key if not using meta_value
            if (newField !== 'meta_value' && newField !== 'meta_value_num') {
              delete updatedSettings.meta_key;
            }
            setSettings(updatedSettings);
            onUpdate(node.id, updatedSettings);
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
        >
          <option value="ID">ID</option>
          <option value="title">Title</option>
          <option value="date">Date</option>
        </select>
      </label>
      {(settings.field === 'meta_value' || settings.field === 'meta_value_num') && (
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Meta Key:
          <input
            type="text"
            value={settings.meta_key || ''}
            onChange={(e) => {
              const updatedSettings = { ...settings, meta_key: e.target.value };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            placeholder="Enter meta key name"
            style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          />
        </label>
      )}
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Direction:
        <div style={{ display: 'flex', gap: '10px', marginTop: '5px' }}>
          <button
            type="button"
            onClick={() => {
              const updatedSettings = { ...settings, direction: 'ASC' };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            style={{
              flex: 1,
              padding: '8px',
              background: settings.direction === 'ASC' ? '#5c4bde' : '#2d3748',
              color: '#fff',
              border: '1px solid #555',
              borderRadius: '4px',
              cursor: 'pointer',
            }}
          >
            ASC ↑
          </button>
          <button
            type="button"
            onClick={() => {
              const updatedSettings = { ...settings, direction: 'DESC' };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            style={{
              flex: 1,
              padding: '8px',
              background: settings.direction === 'DESC' ? '#5c4bde' : '#2d3748',
              color: '#fff',
              border: '1px solid #555',
              borderRadius: '4px',
              cursor: 'pointer',
            }}
          >
            DESC ↓
          </button>
        </div>
      </label>
    </div>
  );

  const renderInclusionExclusionSettings = () => {
    const insertDynamicTag = (tag) => {
      // This will be handled by the input fields with their own dynamic tag buttons
      return tag;
    };

    return (
      <div style={{ position: 'relative' }}>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Include Post IDs (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.postIn || ''}
              onChange={(e) => {
                // Accept any text input including commas - no restrictions
                const newSettings = { ...settings, postIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder="e.g., 1,2,3"
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Exclude Post IDs (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.postNotIn || ''}
              onChange={(e) => {
                const newSettings = { ...settings, postNotIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder="e.g., 1,2,3"
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Author IDs Include (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.authorIn || ''}
              onChange={(e) => {
                const newSettings = { ...settings, authorIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder="e.g., 1,2,3"
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Author IDs Exclude (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.authorNotIn || ''}
              onChange={(e) => {
                const newSettings = { ...settings, authorNotIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder="e.g., 1,2,3"
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
          </div>
        </label>
        <label style={{ display: 'flex', alignItems: 'center', marginBottom: '10px', gap: '10px' }}>
          <input
            type="checkbox"
            checked={settings.ignoreStickyPosts !== false}
            onChange={(e) => {
              const newSettings = { ...settings, ignoreStickyPosts: e.target.checked };
              setSettings(newSettings);
              onUpdate(node.id, newSettings);
            }}
            style={{ width: 'auto' }}
          />
          <span>Ignore Sticky Posts</span>
        </label>
      </div>
    );
  };

  const LOGIC_OPTIONS = [
    { value: 'AND', label: 'AND', description: 'Posts must match ALL connected filters' },
    { value: 'OR', label: 'OR', description: 'Posts can match ANY connected filter' },
    { value: 'UNION', label: 'UNION', description: 'Combines results from separate filter branches, removing duplicates' },
    { value: 'UNION ALL', label: 'UNION ALL', description: 'Combines results from separate filter branches, keeping all duplicates' },
  ];

  const renderLogicSettings = () => (
    <div>
      <div style={{ display: 'block', marginBottom: '10px', fontWeight: '500' }}>Relation:</div>
      {LOGIC_OPTIONS.map((opt) => (
        <label
          key={opt.value}
          style={{
            display: 'block',
            marginBottom: '12px',
            padding: '10px',
            background: (settings.relation || 'AND') === opt.value ? 'rgba(92, 75, 222, 0.2)' : 'transparent',
            border: '1px solid #444',
            borderRadius: '6px',
            cursor: 'pointer',
          }}
        >
          <input
            type="radio"
            name="qf-logic-relation"
            value={opt.value}
            checked={(settings.relation || 'AND') === opt.value}
            onChange={() => {
              const updatedSettings = { ...settings, relation: opt.value };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            style={{ marginRight: '8px' }}
          />
          <span style={{ fontWeight: '500' }}>{opt.label}</span>
          <div style={{ fontSize: '12px', color: '#a0aec0', marginTop: '4px', marginLeft: '24px' }}>
            {opt.description}
          </div>
        </label>
      ))}
    </div>
  );

  const renderJoinSettings = () => (
    <div>
      <div style={{ fontSize: '12px', color: '#999', marginTop: '10px' }}>
        SQL Joins are not available in this version.
      </div>
    </div>
  );

  const flushQueryCache = () => {
    const cfg = typeof window !== 'undefined' ? window.QueryForgeConfig || {} : {};
    const ajaxUrl = cfg.ajaxUrl;
    const nonce = cfg.nonce || '';
    if (!ajaxUrl || !currentLogicJson) {
      return;
    }
    fetch(ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'query_forge_flush_block_cache',
        nonce,
        logic_json: currentLogicJson,
      }),
    })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          alert(__('Cached results for this query were cleared.', 'query-forge'));
        } else {
          alert(res.data?.message || __('Could not clear cache.', 'query-forge'));
        }
      })
      .catch(() => alert(__('Could not clear cache.', 'query-forge')));
  };

  const renderTargetSettings = () => (
    <div>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Posts Per Page:
        <input
          type="number"
          value={settings.postsPerPage || 10}
          onChange={(e) => setSettings({ ...settings, postsPerPage: parseInt(e.target.value) || 10 })}
          min="1"
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        />
      </label>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        {__('Result cache (Free)', 'query-forge')}
        <select
          value={settings.cacheDuration != null ? String(settings.cacheDuration) : '0'}
          onChange={(e) =>
            setSettings({ ...settings, cacheDuration: parseInt(e.target.value, 10) || 0 })
          }
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="0">{__('Off', 'query-forge')}</option>
          <option value="60">{__('1 minute', 'query-forge')}</option>
          <option value="300">{__('5 minutes', 'query-forge')}</option>
          <option value="900">{__('15 minutes', 'query-forge')}</option>
          <option value="3600">{__('1 hour', 'query-forge')}</option>
        </select>
      </label>
      <div style={{ fontSize: '12px', color: '#a0aec0', marginBottom: '10px' }}>
        {__(
          'Caches rendered HTML for faster loads. Cleared when you publish or update a post. Admins and WP_DEBUG always see fresh results.',
          'query-forge'
        )}
      </div>
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation();
          flushQueryCache();
        }}
        style={{
          width: '100%',
          padding: '8px',
          background: '#2d3748',
          color: '#fff',
          border: '1px solid #4a5568',
          borderRadius: '4px',
          cursor: 'pointer',
          marginBottom: '10px',
        }}
      >
        {__('Clear result cache now', 'query-forge')}
      </button>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Order By:
        <select
          value={settings.orderBy || 'date'}
          onChange={(e) => {
            const newOrderBy = e.target.value;
            const allowedOrderBy = ['date', 'title'];
            if (!allowedOrderBy.includes(newOrderBy)) {
              return;
            }
            setSettings({ ...settings, orderBy: newOrderBy });
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="date">Date</option>
          <option value="title">Title</option>
        </select>
      </label>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Order:
        <select
          value={settings.order || 'DESC'}
          onChange={(e) => setSettings({ ...settings, order: e.target.value })}
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="ASC">ASC</option>
          <option value="DESC">DESC</option>
        </select>
      </label>
    </div>
  );

  return (
      <div 
      data-qf-settings-panel
      style={{
      position: 'absolute',
      right: '20px',
      top: '20px',
      width: '300px',
      background: '#1e1e1e',
      border: '1px solid #333',
      borderRadius: '8px',
      padding: '20px',
      color: '#fff',
      zIndex: 1000,
      maxHeight: '80vh',
      overflowY: 'auto'
      }}
      onClick={(e) => e.stopPropagation()}
      onMouseDown={(e) => e.stopPropagation()}
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '15px' }}>
        <h3 style={{ margin: 0 }}>Node Settings</h3>
        <button
          onClick={onClose}
          style={{ background: 'transparent', border: 'none', color: '#fff', cursor: 'pointer' }}
        >
          ×
        </button>
      </div>
      
      {node.type === 'source' && renderSourceSettings()}
      {node.type === 'filter' && renderFilterSettings()}
      {node.type === 'sort' && renderSortSettings()}
      {node.type === 'inclusionExclusion' && renderInclusionExclusionSettings()}
      {node.type === 'join' && renderJoinSettings()}
      {node.type === 'logic' && renderLogicSettings()}
      {node.type === 'target' && renderTargetSettings()}

      <button
        onClick={(e) => {
          e.stopPropagation();
          e.preventDefault();
          handleSave(e);
          onClose(); // Close panel after applying
        }}
        type="button"
        style={{
          width: '100%',
          padding: '10px',
          background: '#5c4bde',
          color: '#fff',
          border: 'none',
          borderRadius: '4px',
          cursor: 'pointer',
          marginTop: '15px'
        }}
      >
        Apply
      </button>
      <div style={{ marginTop: '12px', textAlign: 'center' }}>
        <a href="https://queryforgeplugin.com" target="_blank" rel="noopener noreferrer" style={{ fontSize: '12px', color: '#718096', textDecoration: 'none' }}>Explore Pro</a>
      </div>
      <UpsellModal
        isOpen={upsellModal.isOpen}
        onClose={() => setUpsellModal({ isOpen: false, featureName: '', description: '' })}
        featureName={upsellModal.featureName}
        description={upsellModal.description}
      />
    </div>
  );
};

export default NodeSettingsPanel;

