// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const SourceNode = ({ data, selected, id, onDelete }) => {
  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete && window.confirm('Delete this Source node?')) {
      onDelete(id);
    }
  };

  // Get display text for the source type
  const getSourceDisplayText = () => {
    if (!data) return null;

    // Pro-only source types
    if (data.sourceType === 'user') return 'Users';
    if (data.sourceType === 'comment') return 'Comments';
    if (data.sourceType === 'sql_table') return (data.tableName || 'SQL Table');
    if (data.sourceType === 'rest_api') return 'REST API';

    // New two-level dropdown structure
    if (data.sourceType === 'posts') return 'posts';
    if (data.sourceType === 'pages') return 'pages';
    if (data.sourceType === 'cpts') {
      // Try to get CPT label from config, fallback to postType name
      const qfConfig = window.QueryForgeConfig || {};
      const postTypes = qfConfig.postTypes || [];
      const cpt = postTypes.find(pt => pt.name === data.postType);
      return cpt ? (cpt.label || data.postType) : (data.postType || 'Custom Post Type');
    }

    // Backward compatibility with old 'post_type' structure
    if (!data.sourceType || data.sourceType === 'post_type') {
      return (data.postType || 'Posts');
    }

    return null;
  };

  return (
    <div
      style={{
        padding: '10px 15px',
        background: selected ? '#5c4bde' : '#2d3748',
        color: '#fff',
        borderRadius: '8px',
        minWidth: '150px',
        border: selected ? '2px solid #fff' : '2px solid transparent',
        position: 'relative',
      }}
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
      <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>Source</div>
      {getSourceDisplayText() && (
        <div style={{ fontSize: '12px', opacity: 0.9 }}>
          {getSourceDisplayText()}
        </div>
      )}
      <div style={{ marginTop: '8px', fontSize: '8pt', opacity: 0.85 }}>
        <a
          href="https://queryforgeplugin.com"
          target="_blank"
          rel="noopener noreferrer"
          style={{ color: '#a78bfa', textDecoration: 'none' }}
          onClick={(e) => e.stopPropagation()}
        >
          More nodes? Go Pro.
        </a>
      </div>
      <Handle
        type="source"
        position={Position.Right}
        style={{ background: '#555', width: '10px', height: '10px' }}
      />
    </div>
  );
};

export default SourceNode;

