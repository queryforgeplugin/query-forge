// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const TargetNode = ({ data, selected }) => {
  return (
    <div
      style={{
        padding: '10px 15px',
        background: selected ? '#9f7aea' : '#2d3748',
        color: '#fff',
        borderRadius: '8px',
        minWidth: '150px',
        border: selected ? '2px solid #fff' : '2px solid transparent',
      }}
    >
      <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>Query Output</div>
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

