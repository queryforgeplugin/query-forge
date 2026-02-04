// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const SortNode = ({ data, selected, id, onDelete }) => {
  const fieldLabel = data?.field || 'Field';
  const direction = data?.direction || 'ASC';
  const directionIcon = direction === 'ASC' ? '↑' : '↓';

  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete && window.confirm('Delete this Sort node?')) {
      onDelete(id);
    }
  };

  return (
    <div
      style={{
        background: selected ? '#4a5568' : '#2d3748',
        border: selected ? '2px solid #5c4bde' : '1px solid #4a5568',
        borderRadius: '8px',
        padding: '15px',
        minWidth: '150px',
        color: '#fff',
        position: 'relative',
        boxShadow: selected ? '0 0 10px rgba(92, 75, 222, 0.5)' : '0 2px 4px rgba(0,0,0,0.2)',
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
      <div style={{ fontWeight: 'bold', marginBottom: '5px', fontSize: '14px' }}>
        Sort
      </div>
      <div style={{ fontSize: '12px', color: '#a0aec0', marginBottom: '3px' }}>
        Field: {fieldLabel}
      </div>
      {(data?.field === 'meta_value' || data?.field === 'meta_value_num') && data?.meta_key && (
        <div style={{ fontSize: '11px', color: '#718096', marginBottom: '3px' }}>
          Key: {data.meta_key}
        </div>
      )}
      <div style={{ fontSize: '12px', color: '#a0aec0' }}>
        Order: {directionIcon} {direction}
      </div>
      {data?.sortOrder && (
        <div
          style={{
            position: 'absolute',
            top: '-10px',
            left: '-10px',
            background: '#5c4bde',
            color: '#fff',
            borderRadius: '50%',
            width: '24px',
            height: '24px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '12px',
            fontWeight: 'bold',
            border: '2px solid #1e1e1e',
          }}
        >
          {data.sortOrder}
        </div>
      )}
      <Handle
        type="target"
        position={Position.Left}
        style={{ background: '#5c4bde', width: '10px', height: '10px' }}
      />
      <Handle
        type="source"
        position={Position.Right}
        style={{ background: '#5c4bde', width: '10px', height: '10px' }}
      />
    </div>
  );
};

export default SortNode;

