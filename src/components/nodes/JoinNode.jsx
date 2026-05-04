// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const JoinNode = ({ data, selected, id, onDelete }) => {
  const tableLabel = data?.table || 'Table';
  const aliasLabel = data?.alias || data?.table || 'alias';
  const leftColumn = data?.on?.left || 'ID';
  const rightColumn = data?.on?.right || 'post_id';

  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete && window.confirm('Delete this Join node?')) {
      onDelete(id);
    }
  };

  return (
    <div
      style={{
        background: selected ? '#4a5568' : '#2d3748',
        border: selected ? '2px solid #10b981' : '1px solid #4a5568',
        borderRadius: '8px',
        padding: '15px',
        minWidth: '150px',
        color: '#fff',
        position: 'relative',
        boxShadow: selected ? '0 0 10px rgba(16, 185, 129, 0.5)' : '0 2px 4px rgba(0,0,0,0.2)',
      }}
    >
      {onDelete && (
        <button
          onClick={handleDelete}
          style={{
            position: 'absolute',
            top: '5px',
            right: '5px',
            background: '#e53e3e',
            border: 'none',
            borderRadius: '50%',
            width: '20px',
            height: '20px',
            color: '#fff',
            cursor: 'pointer',
            fontSize: '12px',
            lineHeight: '1',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
          title="Delete"
        >
          ×
        </button>
      )}
      <div style={{ fontWeight: 'bold', marginBottom: '5px', fontSize: '14px', color: '#10b981' }}>
        Join
      </div>
      <div style={{ fontSize: '12px', color: '#a0aec0', marginBottom: '3px' }}>
        Table: {tableLabel}
      </div>
      {aliasLabel !== tableLabel && (
        <div style={{ fontSize: '12px', color: '#a0aec0', marginBottom: '3px' }}>
          Alias: {aliasLabel}
        </div>
      )}
      <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', paddingTop: '5px', borderTop: '1px solid #4a5568' }}>
        {leftColumn} = {rightColumn}
      </div>
      <Handle
        type="target"
        position={Position.Left}
        style={{ background: '#10b981', width: '10px', height: '10px' }}
      />
      <Handle
        type="source"
        position={Position.Right}
        style={{ background: '#10b981', width: '10px', height: '10px' }}
      />
    </div>
  );
};

export default JoinNode;

