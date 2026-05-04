// React is provided by WordPress via wp-element (available globally)
const { useState, useMemo } = React;
import { Handle, Position } from 'reactflow';
import { normalizeFilterNodeData } from '../../utils/normalizeFilterNode.js';

function formatConditionValue(cond) {
  const op = String(cond.operator || '').toUpperCase();
  if (op === 'EXISTS' || op === 'NOT EXISTS') {
    return '[no value]';
  }
  if (op === 'BETWEEN' && cond.value && typeof cond.value === 'object' && !Array.isArray(cond.value)) {
    const a =
      cond.value.from !== undefined && cond.value.from !== ''
        ? String(cond.value.from)
        : '[no value]';
    const b =
      cond.value.to !== undefined && cond.value.to !== ''
        ? String(cond.value.to)
        : '[no value]';
    return `${a} to ${b}`;
  }
  if (cond.value === undefined || cond.value === '') {
    return '[no value]';
  }
  return String(cond.value);
}

/** Short label for canvas copy (e.g. tax:post_tag → tag, post_title → title). */
function formatFieldDisplayName(field) {
  const f = String(field || '').trim();
  if (!f) {
    return 'field';
  }
  if (f.startsWith('tax:')) {
    const slug = f.slice(4);
    const known = {
      post_tag: 'tag',
      category: 'category',
      post_format: 'format',
    };
    if (known[slug]) {
      return known[slug];
    }
    return slug.replace(/_/g, ' ');
  }
  const postMap = {
    post_title: 'title',
    post_name: 'slug',
    post_date: 'date',
    post_modified: 'modified',
    post_author: 'author',
    post_status: 'status',
    post_type: 'type',
    ID: 'ID',
    menu_order: 'menu order',
  };
  if (postMap[f]) {
    return postMap[f];
  }
  if (f.startsWith('post_')) {
    return f.replace(/^post_/, '').replace(/_/g, ' ');
  }
  return f.replace(/_/g, ' ');
}

function quoteSummaryValue(raw) {
  const s = String(raw);
  return `'${s.replace(/'/g, "\\'")}'`;
}

/** One segment: title: 'hello' */
function formatConditionSummaryChunk(cond) {
  const label = formatFieldDisplayName(cond.field);
  const val = formatConditionValue(cond);
  return `${label}: ${quoteSummaryValue(val)}`;
}

/** Full inline summary for multi-condition filters (expanded view). */
function buildMultiConditionExpandedSummary(conditions, relation) {
  if (!conditions.length) {
    return '';
  }
  const relLabel = relation === 'OR' ? 'OR' : 'AND';
  return conditions.map((c) => formatConditionSummaryChunk(c)).join(` (${relLabel}) `);
}

/** Collapsed: first 25 chars of full summary, then ... */
function truncateCollapsedSummary(full, maxChars = 25) {
  if (!full || full.length <= maxChars) {
    return full || '';
  }
  return `${full.slice(0, maxChars)}...`;
}

const FilterNode = ({ data, selected, id, onDelete }) => {
  const [expanded, setExpanded] = useState(false);

  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete && window.confirm('Delete this Filter node?')) {
      onDelete(id);
    }
  };

  const normalized = useMemo(() => normalizeFilterNodeData(data || {}), [data]);
  const { relation, conditions } = normalized;
  const multi = conditions.length > 1;
  const unconnected = data?.unconnected === true;
  const needsVal = data?._needsValidation === true || data?._migrationReviewPending === true;

  let border = '2px solid transparent';
  if (needsVal) {
    border = '2px solid #d69e2e';
  } else if (selected) {
    border = '2px solid #fff';
  } else if (unconnected) {
    border = '1px dashed #718096';
  }

  const toggleExpand = (e) => {
    e.stopPropagation();
    setExpanded((v) => !v);
  };

  const multiExpandedFull = multi
    ? buildMultiConditionExpandedSummary(conditions, relation)
    : '';
  const multiCollapsed = multi ? truncateCollapsedSummary(multiExpandedFull, 25) : '';

  const summarySingle = (
    <div style={{ fontSize: '11px', opacity: 0.9 }}>
      {conditions[0]?.field ? (
        <div>Field: {conditions[0].field}</div>
      ) : (
        <div>Field: </div>
      )}
      {conditions[0]?.operator && <div>Op: {conditions[0].operator}</div>}
      <div>Value: {formatConditionValue(conditions[0] || {})}</div>
    </div>
  );

  const summaryMulti = (
    <div style={{ fontSize: '11px', opacity: 0.95, lineHeight: 1.35 }}>
      {expanded ? (
        <>
          <span>{multiExpandedFull}</span>{' '}
          <button
            type="button"
            className="nodrag nopan"
            onMouseDown={(e) => e.stopPropagation()}
            onClick={toggleExpand}
            style={{
              background: 'none',
              border: 'none',
              padding: 0,
              cursor: 'pointer',
              color: '#f6e05e',
              textDecoration: 'underline',
              fontSize: '11px',
            }}
          >
            less
          </button>
        </>
      ) : (
        <>
          <span>{multiCollapsed}</span>{' '}
          <button
            type="button"
            className="nodrag nopan"
            onMouseDown={(e) => e.stopPropagation()}
            onClick={toggleExpand}
            style={{
              background: 'none',
              border: 'none',
              padding: 0,
              cursor: 'pointer',
              color: '#f6e05e',
              textDecoration: 'underline',
              fontSize: '11px',
            }}
          >
            more
          </button>
        </>
      )}
    </div>
  );

  return (
    <div
      style={{
        padding: '10px 15px',
        background: selected ? '#276749' : '#2d3748',
        color: '#fff',
        borderRadius: '8px',
        minWidth: '150px',
        border,
        opacity: unconnected ? 0.7 : 1,
        position: 'relative',
        cursor: 'default',
      }}
      title={unconnected ? 'Not connected to output' : undefined}
    >
      {onDelete && (
        <button
          type="button"
          onClick={handleDelete}
          style={{
            position: 'absolute',
            top: '5px',
            right: '5px',
            background: 'rgba(255,0,0,0.7)',
            color: '#fff',
            border: 'none',
            borderRadius: '50%',
            width: '20px',
            height: '20px',
            cursor: 'pointer',
            fontSize: '12px',
            lineHeight: '1',
            padding: 0,
          }}
          title="Delete node"
        >
          ×
        </button>
      )}
      <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>
        Filter
        {unconnected && (
          <span style={{ fontSize: '10px', opacity: 0.8, marginLeft: '4px' }}>(not in query)</span>
        )}
        {needsVal && (
          <span
            style={{
              marginLeft: '6px',
              fontSize: '9px',
              fontWeight: 700,
              color: '#d69e2e',
              border: '1px solid #d69e2e',
              borderRadius: '4px',
              padding: '1px 4px',
            }}
            title="Review settings"
          >
            !
          </span>
        )}
      </div>
      {multi ? summaryMulti : summarySingle}
      <Handle
        type="target"
        position={Position.Left}
        style={{ background: '#555', width: '10px', height: '10px' }}
      />
      <Handle
        type="source"
        position={Position.Right}
        style={{ background: '#555', width: '10px', height: '10px' }}
      />
    </div>
  );
};

export default FilterNode;
