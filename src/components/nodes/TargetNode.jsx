// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const BADGE_COLORS = {
  AND: '#2b6cb0',
  OR: '#2c7a7b',
  UNION: '#553c9a',
  'UNION ALL': '#553c9a',
};

const TargetNode = ({ data, selected }) => {
  const summary = data?.outputLogicSummary;
  const badgeBg = summary?.label ? BADGE_COLORS[summary.label] || '#4a5568' : null;

  return (
    <div
      style={{
        padding: '10px 15px',
        background: selected ? '#9f7aea' : '#2d3748',
        color: '#fff',
        borderRadius: '8px',
        minWidth: '150px',
        maxWidth: '220px',
        border: selected ? '2px solid #fff' : '2px solid transparent',
      }}
    >
      <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>Query Output</div>
      {summary && summary.message && (
        <div style={{ marginTop: '6px' }}>
          {summary.label && (
            <span
              style={{
                display: 'inline-block',
                fontSize: '10px',
                fontWeight: 600,
                letterSpacing: '0.02em',
                padding: '2px 6px',
                borderRadius: '4px',
                background: badgeBg,
                color: '#fff',
                marginBottom: '4px',
              }}
            >
              {summary.label}
            </span>
          )}
          <div
            style={{
              fontSize: '10px',
              lineHeight: 1.35,
              color: 'rgba(255,255,255,0.75)',
              marginTop: summary.label ? '4px' : 0,
              overflowWrap: 'break-word',
            }}
          >
            {summary.message}
          </div>
          {data.outputLogicSummary?.advisory && (
            <div
              style={{
                marginTop: '5px',
                fontSize: '10px',
                color: '#a0aec0',
                lineHeight: '1.4',
                overflowWrap: 'break-word',
              }}
            >
              {data.outputLogicSummary.advisory}
            </div>
          )}
        </div>
      )}
      {data && (
        <div style={{ fontSize: '11px', opacity: 0.9, marginTop: '5px' }}>
          {data.postsPerPage && <div>Limit: {data.postsPerPage}</div>}
          {data.orderBy && <div>Order: {data.orderBy} {data.order || 'DESC'}</div>}
        </div>
      )}
      <Handle
        type="target"
        position={Position.Left}
        style={{ background: '#555', width: '10px', height: '10px' }}
      />
    </div>
  );
};

export default TargetNode;

