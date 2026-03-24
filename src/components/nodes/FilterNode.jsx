// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const FilterNode = ({ data, selected, id, onDelete }) => {
  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete && window.confirm('Delete this Filter node?')) {
      onDelete(id);
    }
  };

  const unconnected = data?.unconnected === true;
  return (
    <div
      style={{
        padding: '10px 15px',
        background: selected ? '#48bb78' : '#2d3748',
        color: '#fff',
        borderRadius: '8px',
        minWidth: '150px',
        border: selected ? '2px solid #fff' : unconnected ? '1px dashed #718096' : '2px solid transparent',
        opacity: unconnected ? 0.7 : 1,
        position: 'relative',
      }}
      title={unconnected ? 'Not connected to output' : undefined}
    >
      {onDelete && (
        <button
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
        Filter{unconnected && <span style={{ fontSize: '10px', opacity: 0.8, marginLeft: '4px' }}>(not in query)</span>}
      </div>
      {data && (
        <div style={{ fontSize: '11px', opacity: 0.9 }}>
          {data.field && <div>Field: {data.field}</div>}
          {data.operator && <div>Op: {data.operator}</div>}
          {data.value !== undefined && data.value !== '' && (
            <div>Value: {String(data.value)}</div>
          )}
        </div>
      )}
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

